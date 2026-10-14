-- 000: updated_at を自動更新する共通トリガー関数
-- CREATE OR REPLACE のため、何度実行しても問題ない。

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
