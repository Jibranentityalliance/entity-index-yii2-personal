<?php

namespace app\commands;

use app\models\User;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Loads reference and sample data.
 *
 *   php yii seed/states                    jurisdictions (needed before any entity)
 *   php yii seed/sample                    ~6,000 fake companies from data/sample/entities.json
 *   php yii seed/all                       both
 *   php yii seed/admin you@x.com 'pass'    create (or reset) an admin portal user
 */
class SeedController extends Controller
{
    public $defaultAction = 'all';

    public function actionAll(): int
    {
        $this->actionStates();
        return $this->actionSample();
    }

    /** Upserts the 51 rows of `sources` from data/states.json. */
    public function actionStates(): int
    {
        $rows = json_decode(file_get_contents(Yii::getAlias('@app/data/states.json')), true, 512, JSON_THROW_ON_ERROR);
        $db = Yii::$app->db;
        foreach ($rows as $s) {
            $db->createCommand()->upsert('sources', [
                'state' => $s['code'],
                'state_name' => $s['name'],
                'method' => $s['method'],
            ], ['state_name' => $s['name'], 'method' => $s['method']])->execute();
        }
        $this->stdout(count($rows) . " jurisdictions loaded.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /** Replaces all entity data with the bundled sample data. */
    public function actionSample(): int
    {
        $db = Yii::$app->db;
        if ((int) $db->createCommand('SELECT COUNT(*) FROM sources')->queryScalar() === 0) {
            $this->stderr("Run seed/states first.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $file = Yii::getAlias('@app/data/sample/entities.json');
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $entities = $data['entities'];
        $this->stdout('Loading ' . count($entities) . " sample entities...\n");

        $db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
        foreach (['filings', 'people', 'entity_versions', 'entities', 'registered_agents'] as $t) {
            $db->createCommand("TRUNCATE TABLE `$t`")->execute();
        }
        $db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();

        $tx = $db->beginTransaction();
        try {
            // Registered agents, de-duplicated by name + address.
            $agentIds = [];
            $agentRows = [];
            foreach ($entities as $e) {
                $a = $e['registered_agent'] ?? null;
                if ($a && !isset($agentIds[$a['name'] . '|' . ($a['address'] ?? '')])) {
                    $agentIds[$a['name'] . '|' . ($a['address'] ?? '')] = count($agentIds) + 1;
                    $agentRows[] = [count($agentIds), $a['name'], $a['address'] ?? null, (int) $a['is_commercial_agent']];
                }
            }
            foreach (array_chunk($agentRows, 500) as $chunk) {
                $db->createCommand()->batchInsert('registered_agents', ['id', 'name', 'address', 'is_commercial_agent'], $chunk)->execute();
            }

            $cols = ['id', 'state', 'state_filing_number', 'url_key', 'slug', 'name', 'entity_type', 'status', 'formation_date',
                'filed_at', 'indexed_at', 'principal_address', 'mailing_address', 'county', 'naics_guess', 'naics_label',
                'registered_agent_id', 'source_url'];
            $lastIndexed = [];
            foreach (array_chunk($entities, 500) as $chunk) {
                $ents = $people = $filings = [];
                foreach ($chunk as $e) {
                    $a = $e['registered_agent'] ?? null;
                    $filed = self::ts($e['filed_at']);
                    $indexed = self::ts($e['indexed_at']);
                    $lastIndexed[$e['state']] = max($lastIndexed[$e['state']] ?? '', $indexed);
                    $ents[] = [
                        $e['id'], $e['state'], $e['state_filing_number'], $e['url_key'], $e['slug'], $e['name'], $e['entity_type'],
                        $e['status'], $e['formation_date'], $filed, $indexed, $e['principal_address'], $e['mailing_address'],
                        $e['county'], $e['naics_guess'], $e['naics_label'] ?? null,
                        $a ? $agentIds[$a['name'] . '|' . ($a['address'] ?? '')] : null, $e['source_url'],
                    ];
                    foreach ($e['people'] as $p) {
                        $people[] = [$e['id'], $p['name'], $p['role'], $p['title'] ?? null, $p['address'] ?? null];
                    }
                    foreach ($e['filings'] as $f) {
                        $filings[] = [$e['id'], $f['filing_type'], $f['filing_date'], $f['document_url'] ?? null];
                    }
                }
                $db->createCommand()->batchInsert('entities', $cols, $ents)->execute();
                if ($people) {
                    $db->createCommand()->batchInsert('people', ['entity_id', 'name', 'role', 'title', 'address'], $people)->execute();
                }
                if ($filings) {
                    $db->createCommand()->batchInsert('filings', ['entity_id', 'filing_type', 'filing_date', 'document_url'], $filings)->execute();
                }
            }
            foreach ($lastIndexed as $state => $ts) {
                $db->createCommand()->update('sources', ['last_success_at' => $ts], ['state' => $state])->execute();
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        Yii::$app->cache->flush();
        $this->stdout(count($entities) . " entities loaded.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /** Creates an admin portal user, or resets the password if the email exists. */
    public function actionAdmin(string $email, string $password): int
    {
        $user = User::findOne(['email' => $email]) ?? new User(['email' => $email, 'name' => 'Admin']);
        $user->role = User::ROLE_ADMIN;
        $user->status = User::STATUS_ACTIVE;
        $user->setPassword($password);
        $user->save(false);
        $this->stdout("Admin user {$email} saved.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    private static function ts(string $iso): string
    {
        return (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
