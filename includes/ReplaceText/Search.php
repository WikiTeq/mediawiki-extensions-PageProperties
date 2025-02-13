<?php

/**
 * @credits https://www.mediawiki.org/wiki/Extension:Replace_Text
 */

namespace MediaWiki\Extension\PageProperties\ReplaceText;

use Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

class Search {

	/**
	 * @param string $search
	 * @param array $namespaces
	 * @param string|null $category
	 * @param string|null $prefix
	 * @param bool $use_regex
	 * @return IResultWrapper Resulting rows
	 */
	public static function doSearchQuery(
		$search, $namespaces, $category, $prefix, $use_regex = false
	) {
		// global $wgReplaceTextResultsLimit;

		$dbr = wfGetDB( DB_REPLICA );
		$tables = [ 'page', 'revision', 'text', 'slots', 'content' ];
		$vars = [ 'page_id', 'page_namespace', 'page_title', 'old_text', 'slot_role_id' ];
		if ( $use_regex ) {
			$comparisonCond = self::regexCond( $dbr, 'old_text', $search );
		} else {
			$any = $dbr->anyString();
			$comparisonCond = 'old_text ' . $dbr->buildLike( $any, $search, $any );
		}
		$conds = [
			$comparisonCond,
			'page_namespace' => $namespaces,
			'rev_id = page_latest',
			'rev_id = slot_revision_id',
			'slot_content_id = content_id',
			$dbr->buildIntegerCast( 'SUBSTR(content_address, 4)' ) . ' = old_id'
		];

		self::categoryCondition( $category, $tables, $conds );
		self::prefixCondition( $prefix, $conds );
		$options = [
			'ORDER BY' => 'page_namespace, page_title',
			// ***edited
			// 'LIMIT' => $wgReplaceTextResultsLimit
			// 'LIMIT' => \PageProperties::$queryLimit
		];

		return $dbr->select( $tables, $vars, $conds, __METHOD__, $options );
	}

	/**
	 * @param string|null $category
	 * @param array &$tables
	 * @param array &$conds
	 */
	public static function categoryCondition( $category, &$tables, &$conds ) {
		if ( strval( $category ) !== '' ) {
			$category = Title::newFromText( $category )->getDbKey();
			$tables[] = 'categorylinks';
			$conds[] = 'page_id = cl_from';
			$conds['cl_to'] = $category;
		}
	}

	/**
	 * @param string|null $prefix
	 * @param array &$conds
	 */
	public static function prefixCondition( $prefix, &$conds ) {
		if ( strval( $prefix ) === '' ) {
			return;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$title = Title::newFromText( $prefix );
		if ( $title !== null ) {
			$prefix = $title->getDbKey();
		}
		$any = $dbr->anyString();
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable strval makes this non-null
		$conds[] = 'page_title ' . $dbr->buildLike( $prefix, $any );
	}

	/**
	 * @param IDatabase $dbr
	 * @param string $column
	 * @param string $regex
	 * @return string query condition for regex
	 */
	public static function regexCond( $dbr, $column, $regex ) {
		if ( $dbr->getType() == 'postgres' ) {
			$op = '~';
		} else {
			$op = 'REGEXP';
		}
		return "$column $op " . $dbr->addQuotes( $regex );
	}

	/**
	 * @param string $str
	 * @param array $namespaces
	 * @param string|null $category
	 * @param string|null $prefix
	 * @param bool $use_regex
	 * @return IResultWrapper Resulting rows
	 */
	public static function getMatchingTitles(
		$str,
		$namespaces,
		$category,
		$prefix,
		$use_regex = false
	) {
		$dbr = wfGetDB( DB_REPLICA );

		$tables = [ 'page' ];
		$vars = [ 'page_title', 'page_namespace' ];

		$str = str_replace( ' ', '_', $str );
		if ( $use_regex ) {
			$comparisonCond = self::regexCond( $dbr, 'page_title', $str );
		} else {
			$any = $dbr->anyString();
			$comparisonCond = 'page_title ' . $dbr->buildLike( $any, $str, $any );
		}
		$conds = [
			$comparisonCond,
			'page_namespace' => $namespaces,
		];

		self::categoryCondition( $category, $tables, $conds );
		self::prefixCondition( $prefix, $conds );
		$sort = [ 'ORDER BY' => 'page_namespace, page_title' ];

		return $dbr->select( $tables, $vars, $conds, __METHOD__, $sort );
	}

	/**
	 * Do a replacement on a string.
	 * @param string $text
	 * @param string $search
	 * @param string $replacement
	 * @param bool $regex
	 * @return string
	 */
	public static function getReplacedText( $text, $search, $replacement, $regex ) {
		if ( $regex ) {
			$escapedSearch = addcslashes( $search, '/' );
			return preg_replace( "/$escapedSearch/Uu", $replacement, $text );
		} else {
			return str_replace( $search, $replacement, $text );
		}
	}

	/**
	 * Do a replacement on a title.
	 * @param Title $title
	 * @param string $search
	 * @param string $replacement
	 * @param bool $regex
	 * @return Title|null
	 */
	public static function getReplacedTitle( Title $title, $search, $replacement, $regex ) {
		$oldTitleText = $title->getText();
		$newTitleText = self::getReplacedText( $oldTitleText, $search, $replacement, $regex );
		return Title::makeTitleSafe( $title->getNamespace(), $newTitleText );
	}
}
