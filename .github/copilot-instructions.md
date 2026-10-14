# AKITO DEV — Copilot Instructions

## Project

AKITOの個人ポートフォリオサイト。
コンセプトは「面倒なことを、仕組みにする。」。

## Tech Stack

React / TypeScript / Vite / Tailwind CSS v4 / React Router / Lucide React

- Tailwind は v4。`tailwind.config.js` は作らない。`border-white/6` のような任意の不透明度もそのまま使える
- 不要なライブラリは追加しない。追加が必要な場合は、先に理由を説明する

## Structure

- `src/components/layout/` — Header / Footer など
- `src/components/sections/` — ページセクション
- `src/components/ui/` — 再利用可能なUI
- `src/pages/` — ページ単位のコンポーネント
- `src/data/` — コンテンツデータ（projects.json / content.ts / navigation.json など）
- `src/types/` — TypeScript型定義

## Scope Rules（最重要）

- 頼まれた範囲だけを変更する。ついでのリファクタや整形はしない
- 次のファイルは、明示的に頼まれない限り変更しない
  - `projects.json` / `content.ts` / `navigation.json`
  - ルーティング設定（`App.tsx`）
- 既存のUI・レイアウトは維持する。デザインの変更が必要な場合は、先に提案する
- 指示が曖昧な場合は、実装せずに質問するか、選択肢を示す
- `git commit` / `git push` は行わない

## Coding Rules

- TypeScriptを使用する。`any` は原則使わない
- React Function Component を使用する
- 既存コンポーネントを優先して再利用する
- データとUIは可能な限り分離する
- 同じ className を複数箇所に書く場合は、定数やコンポーネントにまとめる

## Design Direction

- Dark UI（背景 `#080d18`、カード `#0b1220`）、Cyan accent
- Developer / System / Terminal inspired。シンプルで実務的にし、過度な装飾は避ける
- ラベルは等幅フォント・大文字・`[LABEL]` 形式に揃える
- ボタンやリンクなど押す要素は、高さ約40px以上・文字は `text-xs` 以上にする
- モバイル対応を維持する

## Workflow

1. 関連するコンポーネント・型・データを先に読む
2. 必要最小限の変更を行う
3. `npm run build` を実行する。失敗した場合は、変更禁止のファイルを触らずに原因を報告する
4. 最後に、変更したファイルと内容を短く報告する

## Execution

- 読み取り・検証（`git diff` / `git status` / `npm run build` など）は、確認なしで実行してよい
- 次の操作は、承認なしに実行しない
  - 大量削除・不可逆な削除
  - 外部への公開・送信
  - 依存パッケージの追加
- コマンドが失敗したら、同じ操作を繰り返さず、原因を整理して報告する
- 方針が複数あって結果が大きく変わる場合は、実装せずに選択肢と推奨案を示して止まる

## File Header Comments

- 新規ファイル、または責務が変わるファイルで、設計意図がコードから読み取りにくい場合だけ、先頭に短いコメントを書く
  - 書く内容: 責務・使用元・守るルール・設計理由
- コードを読めば分かることは書かない
- 既存ファイルへコメントを一括追加しない

## Language

- ユーザーへの説明・回答は日本語で行う
- 変数名・関数名は英語を使用する
- コメントは必要に応じて日本語で書く
- エラーメッセージの原文は翻訳しない
