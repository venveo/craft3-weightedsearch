<?php
/**
 * Better Search plugin for Craft CMS 3.x
 *
 * A remake of the weighted search plugin for Craft 2
 *
 * @link      https://www.ransom.pw
 * @copyright Copyright (c) 2017 Ransom Roberson
 */

namespace venveo\weightedsearch;

use venveo\weightedsearch\services\EntriesService as EntriesServiceService;
use venveo\weightedsearch\variables\WeightedSearchVariable;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\twig\variables\CraftVariable;

use yii\base\Event;

/**
 * Class WeightedSearch
 *
 * @author    Ransom Roberson
 * @package   WeightedSearch
 * @since     1.0.0
 *
 * @property  EntriesServiceService $entriesService
 */
class WeightedSearch extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var WeightedSearch
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('weightedSearch', WeightedSearchVariable::class);
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );
    }

    // Protected Methods
    // =========================================================================

}
