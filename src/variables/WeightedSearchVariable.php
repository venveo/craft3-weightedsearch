<?php
/**
 * Better Search plugin for Craft CMS 3.x
 *
 * A remake of the weighted search plugin for Craft 2
 *
 * @link      https://www.ransom.pw
 * @copyright Copyright (c) 2017 Ransom Roberson
 */

namespace venveo\weightedsearch\variables;

use venveo\weightedsearch\WeightedSearch;

use Craft;

/**
 * @author    Ransom Roberson
 * @package   WeightedSearch
 * @since     1.0.0
 */
class WeightedSearchVariable {
	// Public Methods
	// =========================================================================

	/**
	 * @param $needle
	 * @param array $sections
	 *
	 * @return array
	 */
	public function substringSearch( $needle, $sections = [] ) {
		$site    = Craft::$app->getSites()->currentSite->id;
		$results = WeightedSearch::$plugin->entriesService->substringSearch( $needle,
			$site, $sections );

		return $results;
	}
}
