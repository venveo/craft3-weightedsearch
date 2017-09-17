<?php
/**
 * Better Search plugin for Craft CMS 3.x
 *
 * A remake of the weighted search plugin for Craft 2
 *
 * @link      https://www.ransom.pw
 * @copyright Copyright (c) 2017 Ransom Roberson
 */

namespace venveo\weightedsearch\services;

use craft\elements\Entry;
use craft\helpers\Search;
use craft\models\FieldLayout;
use craft\models\Section;
use venveo\weightedsearch\WeightedSearch;

use Craft;
use craft\base\Component;
use yii\db\Query;

/**
 * @author    Ransom Roberson
 * @package   WeightedSearch
 * @since     1.0.0
 */
class EntriesService extends Component {
	const MAX_CHARS_BEFORE_KEYWORD = 100;
	const MAX_CHARS_AFTER_KEYWORD = 100;
	const WEIGHT_FOR_PARTIAL_TITLE_MATCH = 1000;
	const WEIGHT_FOR_FULL_TITLE_MATCH = 10000;
	const WEIGHT_FOR_PRIORITIZED_TERM = 100000;


	/**
	 * Search entries for a given substring.
	 *
	 * The search is performed by querying Craft's 'searchindex' table, so some
	 * "normalization" will occur. For instance, the search is
	 * case-insensitive. See StringHelper::normalizeKeywords().
	 *
	 * It is possible to override the default scoring of search results by
	 * defining a Tag field called 'prioritizedSearchTerms'. In a search for
	 * a given term, if there is an entry with that term in its
	 * 'prioritizedSearchTerms' field, that entry will be prioritized.
	 *
	 * Besides this, an entry's score/relevance is calculated based on how many
	 * times the search term/needle appears in the entry's title and fields. In
	 * more detail:
	 * - Each occurrence of the needle in a normal field counts 1.
	 * - If the needle appears in only part of the entry's title, this counts
	 *   1000 per such occurrence.
	 * - If the needle is the entry's entire title, this counts 10000.
	 * - If the needle appears in the 'prioritizedSearchTerms' field, this
	 *   counts 100000.
	 *
	 * @param string $needle  Substring to search for.
	 * @param string $siteId  Site ID to search in
	 * @param array $sections The handles of the sections to search in. If the
	 *                        array is empty, the search will instead cover all sections.
	 *
	 * @return array The search results, with the most relevant results first.
	 *         Each item in the array is itself an array, with the following
	 *         keys:
	 *         'entry': (object) Craft ElementCriteriaModel of entry returned
	 *                    in the search result
	 *         'excerpt': (string) Where the needle was found as part of a
	 *                    larger string of text (e.g. a rich text field), this
	 *                    will show an example of the needle in context,
	 *                    possibly with an ellipsis character (…) at the start
	 *                    and/or end if the field contents were clipped. A
	 *                    maximum of 100 characters (excluding ellipsis) will
	 *                    be shown on each side of the needle.
	 *                    The excerpt will be in HTML format, and instances of
	 *                    the needle will be contained in a <mark> element.
	 *                    The excerpt may be an empty string (if no context was
	 *                    found).
	 *         'score': (int) The entry's relevance to the search query,
	 *                  presented as a positive integer. Higher is better.
	 */
	public function substringSearch( $needle, $siteId, $sections = [] ) {
		$sectionIds = [];
		foreach ( $sections as $section ) {
			/** @var Section $section */
			$sectionRef = Craft::$app->getSections()->getSectionByHandle( $section );
			if ( ! $sectionRef ) {
				continue;
			}
			$id = $sectionRef->id;

			if ( isset( $id ) && $id <> '' && $id != null ) {
				$sectionIds[] = $id;
			}
		}
		$result           = [];
		$normalizedNeedle = Search::normalizeKeywords( $needle );
		$query            = $this->getSearchIndexHits( $normalizedNeedle, $siteId, $sectionIds );
		$results          = $query->all();
		foreach ( $results as $row ) {
			$originalId    = $row['elementId'];
			$entry         = Craft::$app->getEntries()->getEntryById( $originalId, $siteId );
			$viewableEntry = $this->getViewableEntry( $entry );
			if ( $viewableEntry ) {
				$isViewableTitle = ( $row['attribute'] === 'title' &&
				                     $entry === $viewableEntry );
				$weight          = $this->getWeight( $isViewableTitle, $row['keywords'],
					$normalizedNeedle );
				$viewableId      = $viewableEntry->id;
				if ( array_key_exists( $viewableId, $result ) ) {
					$result[ $viewableId ]['score'] += $weight;
				} else {
					$overrideWeight        =
						$this->getOverrideWeight( $viewableEntry, $needle );
					$result[ $viewableId ] = [
						'entry'   => $viewableEntry,
						'excerpt' => '',
						'score'   => ( $overrideWeight + $weight ),
					];
				}
				if ( $result[ $viewableId ]['excerpt'] === '' ) {
					$fieldText                        = $this->getFieldText( $entry, $row['fieldId'] );
					$fieldExtract                     = $this->getExtractHtml( $fieldText, $needle );
					$result[ $viewableId ]['excerpt'] = $fieldExtract;
				}
			}
		}
		usort( $result, function ( $a, $b ) {
			return $b['score'] - $a['score'];
		} );

		return $result;
	}


	private function getSearchIndexHits( $normalizedNeedle, $siteId, $sectionIds ) {
		$escapedNeedle = strtr( $normalizedNeedle,
			[ '%' => '\%', '_' => '\_' ] );

		$query = ( new Query() )
			->select( [ 'elementId', 'attribute', 'fieldId', 'keywords' ] )
			->from( 'searchindex' )
			->where( [ 'siteId' => $siteId ] )
			->andFilterWhere( [
				'or',
				[ 'like', 'attribute', 'field' ],
				[ 'like', 'attribute', 'title' ],
			] )
			->andWhere( [ 'like', 'keywords', $escapedNeedle ] );

		if ( is_array( $sectionIds ) && ! empty( $sectionIds ) ) {
			$query->join( 'LEFT JOIN', 'entries', 'elementId=entries.id' )
			      ->andwhere( [ 'in', 'sectionId', $sectionIds ] );
		}

		return $query;
	}

	/**
	 * @param $entry
	 * @param $needle
	 *
	 * @return int
	 */
	private function getOverrideWeight( Entry $entry, $needle ) {
		$overrideWeight = 0;
		$field          = $entry->fieldLayout->getFieldByHandle( 'prioritizedSearchTerms' );
		if ( ! $field ) {
			return $overrideWeight;
		}
		/** @var FieldLayout $fields */
		$prioritySearchTerms = $entry->getFieldValue( 'prioritizedSearchTerms' );
		if ( $prioritySearchTerms ) {
			foreach ( $prioritySearchTerms as $searchTerm ) {
				if ( $searchTerm->title === $needle ) {
					$overrideWeight = self::WEIGHT_FOR_PRIORITIZED_TERM;
					break;
				}
			}
		}

		return $overrideWeight;
	}


	/**
	 * @param craft\Elements\Entry $entry
	 *
	 * @return Entry
	 */
	private function getViewableEntry( $entry ) {
		$viewableEntry = null;
		if ( $entry && $entry->getStatus() === Entry::STATUS_LIVE ) {
			/* If the entry doesn't have an address by itself, but you would
			 * like to have search hits in its contents count for a different
			 * entry (typically, a "parent" that links to this "child" and that
			 * outputs the "child"'s contents in its template), it is possible
			 * to insert code for it here. (Assign the "parent" to
			 * $viewableEntry instead, in that case.)
			 */
			$viewableEntry = $entry;
		}

		return $viewableEntry;
	}

	private function getWeight( $isViewableTitle, $keywords, $normalizedNeedle ) {
		$weight = 0;
		if ( $isViewableTitle && $normalizedNeedle === trim( $keywords ) ) {
			$weight = self::WEIGHT_FOR_FULL_TITLE_MATCH;
		} else {
			if ( strlen( $normalizedNeedle ) > 0 ) {
				$weight = substr_count( $keywords, $normalizedNeedle );
			}
			if ( $isViewableTitle ) {
				$weight *= self::WEIGHT_FOR_PARTIAL_TITLE_MATCH;
			}
		}

		return $weight;
	}


	private function getFieldText( $entry, $fieldId ) {

		$queryRow = ( new Query() )
			->select( [ 'handle', 'type' ] )
			->from( 'fields' )
			->where( [ 'id' => $fieldId ] )
			->one();

		$fieldType   = $queryRow['type'];
		$fieldHandle = $queryRow['handle'];
		$fieldValue  = '';
		if ( $fieldHandle ) {
			$fieldValue = $entry[ $fieldHandle ];
		}
		if ( $fieldType == 'RichText' ) {
			return $this->htmlToText( $fieldValue );
		} elseif ( $fieldType == 'PlainText' ) {
			return $fieldValue;
		} else {
			return ''; // e.g. Table is not currently supported
		}
	}

	private function htmlToText( $value ) {
		/* This is a hack that assumes the HTML is relatively simple.
		 * Ideally it would be parsed and rendered to a text string
		 * with some appopriate default styling for block elements.
		 */
		$blockElements = [
			'blockquote',
			'div',
			'dd',
			'dl',
			'dt',
			'figure',
			'figcaption',
			'h1',
			'h2',
			'h3',
			'h4',
			'h5',
			'h6',
			'hr',
			'li',
			'ol',
			'p',
			'td',
			'th',
			'ul',
		];
		// Avoid converting "<h3>Heading</h3><p>text</p>" to "Headingtext"
		$htmlWithExtraSpaces = str_replace(
			array_map( function ( $element ) {
				return '<' . $element;
			}, $blockElements ),
			array_map( function ( $element ) {
				return ' <' . $element;
			}, $blockElements ),
			$value
		);

		return html_entity_decode( strip_tags( $htmlWithExtraSpaces ) );
	}

	private function getExtractHtml( $fullString, $substringToHighlight ) {
		$prefix         = '…';
		$postfix        = '…';
		$substringStart = stripos( $fullString, $substringToHighlight );
		$extractStart   = $substringStart - self::MAX_CHARS_BEFORE_KEYWORD;
		if ( $extractStart < 0 ) {
			$extractStart = 0;
			$prefix       = '';
		}
		$extractEnd = $substringStart + strlen( $substringToHighlight ) +
		              self::MAX_CHARS_AFTER_KEYWORD;
		if ( $extractEnd > strlen( $fullString ) ) {
			$extractEnd = strlen( $fullString );
			$postfix    = '';
		}
		$plainExtract          = substr( $fullString, $extractStart,
			$extractEnd - $extractStart );
		$regexEscapedSubstring = preg_quote( $substringToHighlight, '/' );
		$plainExtractParts     = preg_split( '/(' . $regexEscapedSubstring . ')/i',
			$plainExtract, null, PREG_SPLIT_DELIM_CAPTURE );
		$escapedExtractParts   = array_map( 'htmlentities', $plainExtractParts );
		foreach ( $escapedExtractParts as $index => $part ) {
			if ( $index % 2 === 1 ) {
				$escapedExtractParts[ $index ] = '<mark>' . $part . '</mark>';
			}
		}
		$htmlExtract = implode( $escapedExtractParts );

		return $prefix . $htmlExtract . $postfix;
	}


}
