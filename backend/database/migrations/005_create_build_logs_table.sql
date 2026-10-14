-- 005: build_logs テーブル
-- 既存 src/data/buildLogs.json の { date, title, tag } を持つ。
--   logged_at  … 既存 JSON の date（"2026.09" のような年月のみ）を月初の日付として保持する
--   sort_order … 表示順（小さいほど先頭。管理画面で並べ替える）
--   is_public  … 公開フラグ。false は GET /api/build-logs に含めない

CREATE TABLE IF NOT EXISTS build_logs (
    id         BIGSERIAL   PRIMARY KEY,
    title      TEXT        NOT NULL,
    tag        TEXT        NOT NULL,
    logged_at  DATE        NOT NULL,
    sort_order INTEGER     NOT NULL DEFAULT 0,
    is_public  BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- seed を再実行しても重複しないようにする（ON CONFLICT で使用）
CREATE UNIQUE INDEX IF NOT EXISTS build_logs_logged_at_title_key
    ON build_logs (logged_at, title);

-- 表示順の取得用
CREATE INDEX IF NOT EXISTS build_logs_sort_order_idx
    ON build_logs (sort_order, logged_at DESC);

DROP TRIGGER IF EXISTS build_logs_set_updated_at ON build_logs;

CREATE TRIGGER build_logs_set_updated_at
    BEFORE UPDATE ON build_logs
    FOR EACH ROW
    EXECUTE FUNCTION set_updated_at();
