# AKITO DEV — Backend（PHP + PostgreSQL）

既存ポートフォリオ（React）と管理画面向けの API 基盤。

```
公開ページ / 管理画面 → API → PostgreSQL
```

公開ページの作品・制作ログは API から取得します。管理画面は `/admin.html` で利用し、
同じ API を通じて PostgreSQL のデータを管理します。

## 必要なもの

- PHP 8.1 以上（`pdo_pgsql` 拡張）
- PostgreSQL 16（Docker を利用）

XAMPP の PHP は `pdo_pgsql` が既定で無効です。コマンド実行時に読み込んでください。

```bash
php -d extension=pdo_pgsql ...
```

`php.ini` の `;extension=pdo_pgsql` を有効化すれば `-d` の指定は不要になります。

## セットアップ

```bash
cd backend
cp .env.example .env      # 接続情報を必要に応じて編集
docker compose up -d      # PostgreSQL を起動（backend/.env の DB_PORT を使用）
```

接続情報は `.env` のみで管理します（コードへ直書きしません）。
`.env` は Git 管理対象外です。

## Migration

```bash
php -d extension=pdo_pgsql database/migrate.php           # 未適用を実行
php -d extension=pdo_pgsql database/migrate.php --status  # 状態確認
```

適用済みは `schema_migrations` で管理され、再実行しても二重適用されません。

## Seed（既存 JSON を DB へ投入）

```bash
php -d extension=pdo_pgsql database/seed.php
```

`../src/data/*.json` を読み取って投入します。**既存 JSON は変更も削除もしません。**
upsert のため何度実行しても重複しません。

投入元を変えたい場合は `.env` の `SEED_DATA_DIR` を指定します。

## API の起動

```bash
php -d extension=pdo_pgsql -S 127.0.0.1:8080 -t public public/index.php
```

| Method | Path | 説明 |
| --- | --- | --- |
| GET | `/api/health` | DB 接続確認 |
| GET | `/api/projects` | 作品一覧 |
| GET | `/api/projects/{id}` | 作品詳細（`id` は 1 以上の整数） |
| GET | `/api/build-logs` | Build Log 一覧（公開分のみ・`sort_order` 順） |
| POST | `/api/login` | 管理画面ログイン（`{"username", "password"}` → セッション Cookie 発行） |
| POST | `/api/logout` | 管理画面ログアウト（Cookie も期限切れにして削除） |
| GET | `/api/session` | ログイン状態（`authenticated` / `username`） |
| GET | `/api/admin/build-logs` | Build Log 一覧（公開・非公開すべて。**要ログイン**） |
| POST | `/api/build-logs` | Build Log 作成（**要ログイン**。保存後の1件を 201 で返す） |
| PUT | `/api/build-logs/{id}` | Build Log 更新（**要ログイン**。保存後の1件を 200 で返す） |
| DELETE | `/api/build-logs/{id}` | Build Log 削除（**要ログイン**） |
| POST | `/api/projects` | 作品作成（**要ログイン**。保存後の1件を 201 で返す） |
| PUT | `/api/projects/{id}` | 作品更新（**要ログイン**。保存後の1件を 200 で返す） |
| DELETE | `/api/projects/{id}` | 作品削除（**要ログイン**） |

- 成功時はリソースをそのまま JSON で返します
- 失敗時は `{"error": {"code": "...", "message": "..."}}` を返します
- DB へ接続できない場合は 503 を返します

## 管理画面のログイン（セッション）

開発時は Vite を起動しているオリジンの `/admin.html` を開きます（通常は
`http://127.0.0.1:5173/admin.html`）。本番ではフロントエンドの `dist/admin.html` を配信してください。
管理画面で更新した公開中の作品・制作ログは、公開ページの API 表示にも反映されます。

`backend/.env` に 1 ユーザー分の認証情報を設定します。パスワードは平文ではなくハッシュを書きます。

```bash
cd backend
php tools/hash-password.php "パスワード"
```

出力された行を `.env` の `ADMIN_PASSWORD_HASH` へ貼り付けます。ハッシュには `$`
が含まれるため、**シングルクォートで囲む**か、**`$` を `$$` にエスケープ**してください
（docker compose やシェルの変数展開対策）。どちらで書いても読み込み時に `$`
へ戻すため、`password_verify` はどちらの書き方でも通ります。

- Cookie は `HttpOnly` / `SameSite=Lax`。`Secure` は https のときだけ付きます
  （http の開発では付けません。付けると Cookie が送信されないため）
- ログイン失敗時は約 1 秒待ちます。ユーザー名が一致しない場合も `password_verify()`
  を実行し、応答時間を揃えます
- 登録・更新 API（POST / PUT）は JSON のみ受け付けます。
  `Content-Type: application/json` が無い場合は 415 を返します
- 未ログインで保護された API を呼ぶと 401 `unauthorized` を返します
- 重複時のコード: `slug_conflict`（作品の slug）/ `display_order_conflict`（作品の表示順）/
  `build_log_conflict`（Build Log の日付＋タイトル）

Apache / nginx へ配置する場合は、`public/` をドキュメントルートにし、
存在しないパスを `public/index.php` へ転送する rewrite 設定を追加してください。

## テスト

```bash
php -d extension=pdo_pgsql tests/run.php
```

- DB が無い環境では DB / HTTP のテストは SKIP されます
- ユーティリティ確認は DB 無しでも実行されます

## ディレクトリ

```
backend/
├── config/            接続設定（.env から組み立て）
├── database/
│   ├── migrations/    SQL migration
│   ├── migrate.php    migration 実行
│   └── seed.php       seed 実行
├── public/
│   └── index.php      フロントコントローラー（唯一の入口）
├── src/
│   ├── controllers/   HealthController / ProjectController / BuildLogController / AuthController
│   │   ├── models/        DB の行を表すクラス
│   │   ├── repositories/  SQL を持つ層
│   │   ├── routes/        ルート定義
│   │   ├── utils/         Router / Database / Env / Session / Auth / Request など
│   │   └── bootstrap.php  オートローダーと .env 読み込み
└── tests/
    └── run.php        依存ライブラリなしのテストランナー
```
