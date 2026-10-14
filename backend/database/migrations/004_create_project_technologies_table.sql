-- 004: project_technologies テーブル（projects と technologies の中間テーブル）
-- 既存 projects.json の stack 配列の並び順を保持するため sort_order を持たせる。

CREATE TABLE IF NOT EXISTS project_technologies (
    project_id    BIGINT   NOT NULL REFERENCES projects (id)     ON DELETE CASCADE,
    technology_id BIGINT   NOT NULL REFERENCES technologies (id) ON DELETE CASCADE,
    sort_order    SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (project_id, technology_id)
);

-- 技術からプロジェクトを引く用
CREATE INDEX IF NOT EXISTS project_technologies_technology_id_idx
    ON project_technologies (technology_id);
