-- 002: skills テーブル
-- 既存 src/data/skills.json は { title, items[] } のグループ構造のため、次のように対応させる。
--   category … グループ名（Frontend / Backend / Infrastructure / Focus）
--   name     … items の各項目（React など）
--   sort_order … 全体通しの表示順（グループの並び順もこれで表現できる）
--   description … 既存 JSON に無いため NULL（将来の管理画面用に用意）

CREATE TABLE IF NOT EXISTS skills (
    id          BIGSERIAL   PRIMARY KEY,
    category    TEXT        NOT NULL,
    name        TEXT        NOT NULL,
    description TEXT,
    sort_order  SMALLINT    NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- (category, name) を一意キーにする（seed の ON CONFLICT で使用）
CREATE UNIQUE INDEX IF NOT EXISTS skills_category_name_key ON skills (category, name);

-- 表示順での取得用
CREATE INDEX IF NOT EXISTS skills_sort_order_idx ON skills (sort_order);

DROP TRIGGER IF EXISTS skills_set_updated_at ON skills;

CREATE TRIGGER skills_set_updated_at
    BEFORE UPDATE ON skills
    FOR EACH ROW
    EXECUTE FUNCTION set_updated_at();
