-- Run once on the EntityIndex server as root:  sudo mariadb < docs/mirror-triggers.sql
-- Records every insert/update/delete that replication applies to the mirrored manual_companies table, so
-- `php yii mirror/apply` (cron) can update the portal's companies. Needs the site migration
-- m261021_000001_mirror_entities (table entityindex.mirror_changes) first.
-- Also add `slave_run_triggers_for_rbr = YES` to /etc/mysql/mariadb.conf.d/60-entityindex-replica.cnf so it survives restarts.

SET GLOBAL slave_run_triggers_for_rbr = 'YES';

CREATE OR REPLACE TRIGGER ein_skip_tracing.manual_companies_ai AFTER INSERT ON ein_skip_tracing.manual_companies
    FOR EACH ROW INSERT INTO entityindex.mirror_changes (source_id) VALUES (NEW.id);
CREATE OR REPLACE TRIGGER ein_skip_tracing.manual_companies_au AFTER UPDATE ON ein_skip_tracing.manual_companies
    FOR EACH ROW INSERT INTO entityindex.mirror_changes (source_id) VALUES (NEW.id);
CREATE OR REPLACE TRIGGER ein_skip_tracing.manual_companies_ad AFTER DELETE ON ein_skip_tracing.manual_companies
    FOR EACH ROW INSERT INTO entityindex.mirror_changes (source_id) VALUES (OLD.id);
