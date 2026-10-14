-- 001: projects テーブル
-- 既存 src/data/projects.json の項目に対応させる。
--   number / accent / visual        … JSON 側の表示用データ
--   links.github / links.demo       … github_url / demo_url
--   features / architecture         … PostgreSQL の text[]（並び順を保持する）
-- なお JSON には thumbnail / status は存在しないため作成していない。

CREATE TABLE IF NOT EXISTS projects (
    id            BIGSERIAL   PRIMARY KEY,
    display_order SMALLINT    NOT NULL UNIQUE,
    slug          TEXT        NOT NULL UNIQUE,
    title         TEXT        NOT NULL,
    description   TEXT        NOT NULL,
    accent        TEXT        NOT NULL,
    visual        TEXT        NOT NULL,
    overview      TEXT,
    problem       TEXT,
    solution      TEXT,
    features      TEXT[]      NOT NULL DEFAULT '{}',
    architecture  TEXT[]      NOT NULL DEFAULT '{}',
    github_url    TEXT        NOT NULL DEFAULT '',
    demo_url      TEXT        NOT NULL DEFAULT '',
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 再実行しても壊れないように、削除してから作り直す
DROP TRIGGER IF EXISTS projects_set_updated_at ON projects;

CREATE TRIGGER projects_set_updated_at
    BEFORE UPDATE ON projects
    FOR EACH ROW
    EXECUTE FUNCTION set_updated_at();
