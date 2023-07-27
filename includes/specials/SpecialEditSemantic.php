<?php

/**
 * This file is part of the MediaWiki extension PageProperties.
 *
 * PageProperties is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * PageProperties is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PageProperties.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author thomas-topway-it <thomas.topway.it@mail.com>
 * @copyright Copyright ©2021-2022, https://wikisphere.org
 */

class SpecialEditSemantic extends SpecialPage {

	protected $title;
	protected $wikiPage;
	protected $user;
	protected $semanticForms = [];

	/** @inheritDoc */
	public function __construct() {
		$listed = false;

		// https://www.mediawiki.org/wiki/Manual:Special_pages
		parent::__construct( 'EditSemantic', '', $listed );
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		// $this->msg( 'pageproperties' )->text()
		return ( $this->title ? ( !$this->title->isKnown() ? $this->msg( "pageproperties-editsemantic-creating" )->text() : $this->msg( "pageproperties-editsemantic-editing" )->text() )
			. ' ' . $this->title . ' ' . $this->msg( "pageproperties-editsemantic-semantic" )->text() : $this->msg( "pageproperties-editsemantic-newpageforms", implode( ', ', $this->semanticForms ) )->text() );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( $this->getRobotPolicy() );

		$user = $this->getUser();

		$this->user = $user;

		// This will throw exceptions if there's a problem
		// $this->checkExecutePermissions( $user );

		$securityLevel = $this->getLoginSecurityLevel();

		if ( $securityLevel !== false && !$this->checkLoginSecurityLevel( $securityLevel ) ) {
			$this->displayRestrictionError();
			return;
		}

		if ( !defined( 'SMW_VERSION' ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->addHelpLink( 'Extension:PageProperties' );

		if ( !$user->isAllowed( 'pageproperties-caneditsemanticproperties' ) ) {
			$this->displayRestrictionError();
			return;
		}

		if ( $par ) {
			// NS_MAIN is ignored if $par is prefixed
			$title = Title::newFromText( $par, NS_MAIN );
			$this->title = $title;
			$this->wikiPage = \PageProperties::getWikiPage( $this->title );
		}

		$this->setData( $out );

		$out->setPageTitle( $this->getDescription() );

		$out->addModules( 'ext.PageProperties.EditSemantic' );
	}

	/**
	 * @param OutputPage $out
	 * @return array
	 */
	private function setData( $out ) {
		$semanticForms = [];
		$semanticData = [];
		$pageCategories = [];

		if ( $this->title && $this->title->isKnown() ) {
			$pageProperties = \PageProperties::getPageProperties( $this->title );

			if ( $pageProperties === false ) {
				$pageProperties = [];
			}

			$defaultValues = [
				'semantic-properties' => [],
				'semantic-forms' => [],
			];

			$pageProperties = array_replace_recursive( $defaultValues, $pageProperties );

			$semanticData = self::getSemanticData( $this->title, $pageProperties );

			if ( is_array( $pageProperties['semantic-forms'] ) ) {
				$semanticForms = $pageProperties['semantic-forms'];
			}

			$pageCategories = $this->getPageCategories();
		}

		$formVal = $this->getRequest()->getVal( 'form' );

		if ( $formVal ) {
			// '|' in titles is forbidden, so we use it as a separator
			$forms_ = array_reverse( explode( '|', $formVal ) );
			foreach ( $forms_ as $title_text_ ) {
				$title_ = Title::makeTitleSafe( NS_PAGEPROPERTIESFORM, $title_text_ );
				if ( $title_ && $title_->isKnown() ) {
					array_unshift( $semanticForms, $title_->getText() );
				}
			}
		}

		$semanticForms = array_unique( $semanticForms );

		\PageProperties::setForms( $out, $semanticForms );

		foreach ( $semanticForms as $key => $value ) {
			if ( empty( \PageProperties::$forms[$value] ) ) {
				unset( $semanticForms[$key] );
			}
		}

		$this->semanticForms = $semanticForms;

		$freetext = null;
		if ( $this->title && $this->title->isKnown() ) {
			$wikiPage = \PageProperties::getWikiPage( $this->title );
			foreach ( $semanticForms as $value ) {
				if ( !empty( \PageProperties::$forms[$value]['freetext-input'] ) && \PageProperties::$forms[$value]['freetext-input'] === "show always" ) {
					$freetext = $wikiPage->getContent( \MediaWiki\Revision\RevisionRecord::RAW )->getNativeData();
					break;
				}
			}
		}

		// add single properties to semanticProperties list
		foreach ( $semanticData as $label => $value ) {
			 \PageProperties::$semanticProperties[] = $label;
		}

		$formID = \PageProperties::formID( $this->title ? $this->title : $out->getTitle(), $semanticForms, 1 );

		$pageForms = [
			$formID => [
				'forms' => $semanticForms,
				'options' => [],
			]
		];

		$sessionData = [
			'formID' => $formID,
			'freetext' => $freetext,
			'properties' => $semanticData,
			'pageCategories' => $pageCategories,
			'errors' => [],
		];

		\PageProperties::addJsConfigVars( $out, [
			'pageForms' => $pageForms,
			'sessionData' => $sessionData,

			'config' => [
				'context' => 'EditSemantic',
				'loadedData' => [],
				'targetPage' => ( $this->title ? $this->title->getText() : null ),
			]
		] );

		// @see SpecialRecentChanges
		$loadingContainer = Html::rawElement(
			'div',
			[ 'class' => 'rcfilters-head mw-rcfilters-head', 'id' => 'mw-rcfilters-spinner-wrapper', 'style' => 'position: relative' ],
			Html::rawElement(
				'div',
				[ 'class' => 'mw-rcfilters-spinner', 'style' => 'margin-top: auto; top: 25%' ],
				Html::element(
					'div',
					[ 'class' => 'mw-rcfilters-spinner-bounce' ]
				)
			)
		);

		$out->addHTML( Html::rawElement( 'div', [
				'id' => 'pagepropertiesform-wrapper-' . $formID,
				'class' => 'pagepropertiesform-wrapper'
			], $loadingContainer )
		);
	}

	/**
	 * @return array
	 */
	private function getPageCategories() {
		if ( !$this->title || !$this->title->isKnown() ) {
			return [];
		}

		// $TitleArray = $this->wikiPage->getCategories();
		// return array_map( static function ( $value ) {
		// 	return $value->getText();
		// }, $TitleArray ) ;

		$ret = [];
		$TitleArray = $this->wikiPage->getCategories();
		foreach ( $TitleArray as $title ) {
			$ret[] = $title->getText();
		}
		return $ret;
	}

	/**
	 * @param Title $title
	 * @param array $pageProperties
	 * @return array
	 */
	private function getSemanticData( $title, $pageProperties ) {
		// *** uncomment to include properties manually annotated on the page
		// $semanticData = \PageProperties::getSemanticData( $title );
		$semanticData = [];

		$registeredPageProperties = [];
		foreach ( $pageProperties['semantic-properties'] as $key => $value ) {
			if ( !empty( $pageProperties['transformed-properties'][$key] ) ) {
				$value = $pageProperties['transformed-properties'][$key];
			}
			$registeredPageProperties[$key] = ( is_array( $value ) ? $value : [ $value ] );
		}

		// merge with the recorded properties
		return array_merge( $semanticData, $registeredPageProperties );
	}
}
