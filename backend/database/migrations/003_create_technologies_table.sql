-- 003: technologies テーブル
-- 既存 projects.json の stack 配列の各要素を1件として登録する。
-- category は既存 JSON に分類情報が無いため NULL 可（将来の管理画面用に用意）。

CREATE TABLE IF NOT EXISTS technologies (
    id         BIGSERIAL   PRIMARY KEY,
    name       TEXT        NOT NULL,
    category   TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 同名の技術を重複登録しない（seed の ON CONFLICT で使用）
CREATE UNIQUE INDEX IF NOT EXISTS technologies_name_key ON technologies (name);

DROP TRIGGER IF EXISTS technologies_set_updated_at ON technologies;

CREATE TRIGGER technologies_set_updated_at
    BEFORE UPDATE ON technologies
    FOR EACH ROW
    EXECUTE FUNCTION set_updated_at();
