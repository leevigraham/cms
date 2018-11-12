<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\helpers;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\i18n\Locale;
use craft\web\twig\variables\Paginate;
use yii\base\BaseObject;
use yii\base\UnknownMethodException;
use yii\db\Exception;

/**
 * Class Sequence
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.31
 */
class Sequence
{
    /**
     * Returns the next number in a given sequence.
     *
     * @param string $name The sequence name.
     * @param int|null $length The minimum string length that should be returned. (Numbers that are too short will be left-padded with `0`s.)
     * @return integer|string
     * @throws Exception if a lock could not be acquired for the sequence
     * @throws \Throwable if reasons
     */
    public static function next(string $name, int $length = null)
    {
        $mutex = Craft::$app->getMutex();
        $lockName = 'seq--' . str_replace(['/', '\\'], '-', $name);

        if (!$mutex->acquire($lockName, 5)) {
            throw new Exception('Could not acquire a lock for the sequence "' . $name . '".');
        }

        try {
            $num = (int)(new Query())
                ->select(['next'])
                ->from('{{%sequences}}')
                ->where(['name' => $name])
                ->scalar() ?: 1;

            if ($num === 1) {
                Craft::$app->getDb()->createCommand()
                    ->insert('{{%sequences}}', ['name' => $name, 'next' => $num + 1], false)
                    ->execute();
            } else {
                Craft::$app->getDb()->createCommand()
                    ->update('{{%sequences}}', ['next' => $num + 1], ['name' => $name], [], false)
                    ->execute();
            }
        } catch (\Throwable $e) {
            $mutex->release($lockName);
            throw $e;
        }

        $mutex->release($lockName);

        if ($length !== null) {
            return str_pad($num, $length, '0', STR_PAD_LEFT);
        }

        return $num;
    }
}
