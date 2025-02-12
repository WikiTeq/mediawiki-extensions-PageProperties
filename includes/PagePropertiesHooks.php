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
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2021-2022, https://wikisphere.org
 */

use MediaWiki\MediaWikiServices;

define( 'SLOT_ROLE_PAGEPROPERTIES', 'pageproperties' );
define( 'CONTENT_MODEL_PAGEPROPERTIES_HTML', 'html' );
define( 'CONTENT_MODEL_PAGEPROPERTIES_SEMANTIC', 'pageproperties-semantic' );

class PagePropertiesHooks {
	/**
	 * @var array
	 */
	private static $SlotsParserOutput = [];

	/**
	 * @param array $credits
	 * @return void
	 */
	public static function initExtension( $credits = [] ) {
		// see includes/specialpage/SpecialPageFactory.php

		$GLOBALS['wgSpecialPages']['PageProperties'] = [
			'class' => \SpecialPageProperties::class,
			'services' => [
				'ContentHandlerFactory',
				'ContentModelChangeFactory',
				// MW 1.36+
				( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ? 'WikiPageFactory'
				// ***whatever other class
				: 'PermissionManager' )
			]
		];

		// *** important! otherwise Page information (action=info) will display a wrong value
		$GLOBALS['wgPageLanguageUseDB'] = true;
		$GLOBALS['wgAllowDisplayTitle'] = true;
		$GLOBALS['wgRestrictDisplayTitle'] = false;
	}

	/**
	 * @param MediaWikiServices $services
	 * @return void
	 */
	public static function onMediaWikiServices( $services ) {
		$services->addServiceManipulator( 'SlotRoleRegistry', static function ( \MediaWiki\Revision\SlotRoleRegistry $registry ) {
			if ( !$registry->isDefinedRole( SLOT_ROLE_PAGEPROPERTIES ) ) {
				$registry->defineRoleWithModel( SLOT_ROLE_PAGEPROPERTIES, CONTENT_MODEL_PAGEPROPERTIES_SEMANTIC, [
					"display" => "none",
					"region" => "center",
					"placement" => "append"
				] );
			}
		} );
	}

	/**
	 * Register any render callbacks with the parser
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'pageproperties', [ \PageProperties::class, 'parserFunctionPageproperties' ] );
		$parser->setFunctionHook( 'pagepropertiesform', [ \PageProperties::class, 'parserFunctionPagepropertiesForm' ] );
		$parser->setFunctionHook( 'pagepropertiesformbutton', [ \PageProperties::class, 'parserFunctionPagepropertiesFormButton' ] );
	}

	/**
	 * @see https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/master/docs/examples/hook.property.initproperties.md
	 * @param SMW\PropertyRegistry $propertyRegistry
	 * @return void
	 */
	public static function onSMWPropertyinitProperties( SMW\PropertyRegistry $propertyRegistry ) {
		// @TODO move to namespace PagePropertiesProperty
		$defs = [
			'__pageproperties_preferred_input' => [
				'label' => 'Preferred input',
				'type' => '_txt',
				// MW message key
				'alias' => 'pageproperties-property-preferred-input',
				'viewable' => true,
				'annotable' => true
			],
			'__pageproperties_input_config' => [
				'label' => 'Input config',
				'type' => '_txt',
				// MW message key
				'alias' => 'pageproperties-property-input-config',
				'viewable' => false,
				'annotable' => false
			],
			'__pageproperties_allows_multiple_values' => [
				'label' => 'Allows multiple values',
				'type' => '_boo',
				// MW message key
				'alias' => 'pageproperties-property-allows-multiple-values',
				'viewable' => true,
				'annotable' => true
			],
			'__pageproperties_semantic_form' => [
				'label' => 'Semantic form',
				'type' => '_txt',
				// MW message key
				'alias' => 'pageproperties-semantic-form',
				'viewable' => false,
				'annotable' => false
			]
		];

		foreach ( $defs as $propertyId => $definition ) {
			$propertyRegistry->registerProperty(
				$propertyId,
				$definition['type'],
				$definition['label'],
				$definition['viewable'],
				$definition['annotable']
			);

			$propertyRegistry->registerPropertyAlias(
				$propertyId,
				wfMessage( $definition['alias'] )->text()
			);

			$propertyRegistry->registerPropertyAliasByMsgKey(
				$propertyId,
				$definition['alias']
			);

		}

		return true;
	}

	/**
	 * @param Title &$title
	 * @param null $unused
	 * @param OutputPage $output
	 * @param User $user
	 * @param WebRequest $request
	 * @param MediaWiki $mediaWiki
	 * @return void
	 */
	public static function onBeforeInitialize( \Title &$title, $unused, \OutputPage $output, \User $user, \WebRequest $request, \MediaWiki $mediaWiki ) {
		\PageProperties::initialize();

		if ( !empty( $GLOBALS['wgPagePropertiesShowSlotsNavigation'] ) && isset( $_GET['slot'] ) ) {
			$slot = $_GET['slot'];
			$slots = \PageProperties::getSlots( $title );

			// set content model of active slot
			$model = $slots[ $slot ]->getModel();
			$title->setContentModel( $model );
		}
	}

	/**
	 * @param Content $content
	 * @param Title $title
	 * @param int $revId
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput &$output
	 * @return void
	 */
	public static function onContentGetParserOutput( $content, $title, $revId, $options, $generateHtml, &$output ) {
		if ( !empty( $GLOBALS['wgPagePropertiesShowSlotsNavigation'] ) && isset( $_GET['slot'] ) ) {
			$slot = $_GET['slot'];
			$slots = \PageProperties::getSlots( $title );

			$slot_content = $slots[ $slot ]->getContent();
			$contentHandler = $slot_content->getContentHandler();

			// @todo: find a more reliable method
			if ( class_exists( 'MediaWiki\Content\Renderer\ContentParseParams' ) ) {
				// see includes/content/AbstractContent.php
				$cpoParams = new MediaWiki\Content\Renderer\ContentParseParams( $title, $revId, $options, $generateHtml );
				$contentHandler->fillParserOutputInternal( $slot_content, $cpoParams, $output );

			} else {
				// @todo: find a more reliable method
				$output = MediaWikiServices::getInstance()->getParser()
					->parse( $slot_content->getText(), $title, $options, true, true, $revId );
			}
			// this will prevent includes/content/AbstractContent.php
			// fillParserOutput from running
			return false;
		}
	}

	/**
	 * called before onPageSaveComplete
	 * @see https://gerrit.wikimedia.org/g/mediawiki/core/+/master/includes/Storage/PageUpdater.php
	 * @param WikiPage $wikiPage
	 * @param MediaWiki\Revision\RevisionRecord $rev
	 * @param int $originalRevId
	 * @param User $user
	 * @param array &$tags
	 * @return void
	 */
	public static function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ) {
		// *** this shouldn't be anymore necessary, since
		// we update the slots cache *while* the new slot contents are saved
		// and we delete the cache if the update fails
		// \PageProperties::emptySlotsCache( $wikiPage->getTitle() );
	}

	/**
	 * @param Content $content
	 * @param Title $title
	 * @param ParserOutput &$parserOutput
	 * @return void
	 */
	public static function onContentAlterParserOutput( Content $content, Title $title, ParserOutput &$parserOutput ) {
		$page_properties = \PageProperties::getPageProperties( $title );

		if ( !empty( $page_properties['page-properties']['categories'] ) ) {
			foreach ( $page_properties['page-properties']['categories'] as $category ) {
				if ( !empty( $category ) ) {
					$parserOutput->addCategory( str_replace( ' ', '_', $category ), ( version_compare( MW_VERSION, '1.38', '<' ) ? $parserOutput->getProperty( 'defaultsort' ) : null ) );
				}
			}
		}

		if ( !defined( 'SMW_VERSION' ) ) {
			return;
		}

		if ( !method_exists( ParserOutput::class, 'mergeMapStrategy' ) ) {
			$semanticData = $parserOutput->getExtensionData( \SMW\ParserData::DATA_ID );
			if ( !( $semanticData instanceof \SMW\SemanticData ) ) {
				$semanticData = new SMW\SemanticData( SMW\DIWikiPage::newFromTitle( $title ) );
			}
			\PageProperties::updateSemanticData( $semanticData, 'onContentAlterParserOutput' );
			$parserOutput->setExtensionData( \SMW\ParserData::DATA_ID, $semanticData );
			return;
		}

		// *** this is an hack to prevent the error "Cannot use object of type SMW\SemanticData as array"
			// includes/parser/ParserOutput.php(2297) function mergeMapStrategy
			// includes/parser/ParserOutput.php(2163): ParserOutput::mergeMapStrategy()
			// includes/Revision/RevisionRenderer.php(271): ParserOutput->mergeHtmlMetaDataFrom()
			// includes/Revision/RevisionRenderer.php(158): MediaWiki\Revision\RevisionRenderer->combineSlotOutput()
		// *** it is only necessary from 1.38 and higher versions
		// *** remove SemanticData from the first slot(s) and
		// *** attach in the pageproperties slot (it will be merged in the combined output)

		$key = $title->getFullText();
		if ( !array_key_exists( $key, self::$SlotsParserOutput ) ) {
			$slots = \PageProperties::getSlots( $title );

			if ( !$slots ) {
				return;
			}

			if ( !array_key_exists( SLOT_ROLE_PAGEPROPERTIES, $slots ) ) {
				return;
			}

			self::$SlotsParserOutput[ $key ] = [ 'content' => $slots[SLOT_ROLE_PAGEPROPERTIES]->getContent() ];
		}

		if ( !self::$SlotsParserOutput[ $key ][ 'content' ]->equals( $content ) ) {
			$semanticData = $parserOutput->getExtensionData( \SMW\ParserData::DATA_ID );
			if ( !( $semanticData instanceof \SMW\SemanticData ) ) {
				$semanticData = new SMW\SemanticData( SMW\DIWikiPage::newFromTitle( $title ) );
			}
			\PageProperties::updateSemanticData( $semanticData, 'onContentAlterParserOutput' );
			self::$SlotsParserOutput[ $key ]['data'] = $semanticData;
			$parserOutput->setExtensionData( \SMW\ParserData::DATA_ID, null );

		// *** this assumes that pageproperties is the last slot, isn't so ?
		} elseif ( !empty( self::$SlotsParserOutput[ $key ]['data'] ) ) {
			$parserOutput->setExtensionData( \SMW\ParserData::DATA_ID, self::$SlotsParserOutput[ $key ]['data'] );
		}
	}

	/**
	 * @param Parser $parser
	 * @param string &$text
	 */
	public static function onParserAfterTidy( Parser $parser, &$text ) {
		$title = $parser->getTitle();

		if ( !$title->isKnown() ) {
			return;
		}

		if ( empty( $GLOBALS['wgPagePropertiesAddTrackingCategory'] ) ) {
			return;
		}

		$page_properties = \PageProperties::getPageProperties( $title );

		if ( !empty( $page_properties ) ) {
			$parser->addTrackingCategory( 'pageproperties-tracking-category' );
		}
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/MultiContentSave
	 * @param RenderedRevision $renderedRevision
	 * @param UserIdentity $user
	 * @param CommentStoreComment $summary
	 * @param int $flags
	 * @param Status $hookStatus
	 * @return void
	 */
	public static function onMultiContentSave( MediaWiki\Revision\RenderedRevision $renderedRevision, MediaWiki\User\UserIdentity $user, CommentStoreComment $summary, $flags, Status $hookStatus ) {
		$revision = $renderedRevision->getRevision();
		$title = Title::newFromLinkTarget( $revision->getPageAsLinkTarget() );

		$displaytitle = \PageProperties::getDisplayTitle( $title );

		// this will store displaytitle in the page_props table
		if ( $displaytitle !== false ) {
			// getRevisionParserOutput();
			$out = $renderedRevision->getSlotParserOutput( MediaWiki\Revision\SlotRecord::MAIN );
			if ( method_exists( $out, 'setPageProperty' ) ) {
				$out->setPageProperty( 'displaytitle', $displaytitle );
			} else {
				$out->setProperty( 'displaytitle', $displaytitle );
			}
		}
	}

	/**
	 * @param string &$confstr
	 * @param User $user
	 * @param array &$forOptions
	 * @return void
	 */
	public static function onPageRenderingHash( &$confstr, User $user, &$forOptions ) {
		if ( !empty( $GLOBALS['wgPagePropertiesShowSlotsNavigation'] ) && isset( $_GET['slot'] ) ) {
			$confstr .= '!' . $_GET['slot'];
		}
	}

	/**
	 * @param OutputPage $out
	 * @param ParserOutput $parserOutput
	 * @return void
	 */
	public static function onOutputPageParserOutput( OutputPage $out, ParserOutput $parserOutput ) {
		$parserOutput->addWrapperDivClass( 'pageproperties-content-model-' . $out->getTitle()->getContentModel() );

		if ( $parserOutput->getExtensionData( 'pagepropertiesform' ) !== null ) {
			$pageForms = $parserOutput->getExtensionData( 'pagepropertiesforms' );

			\PageProperties::addJsConfigVars( $out, [
				'pageForms' => $pageForms,
				'config' => [
					'context' => 'parserfunction',
					// 'loadedData' => [],
				]
			] );

			$out->addModules( 'ext.PageProperties.EditSemantic' );
		}
	}

	/**
	 * @param Skin $skin
	 * @param array &$bar
	 * @return void
	 */
	public static function onSkinBuildSidebar( $skin, &$bar ) {
		if ( !empty( $GLOBALS['wgPagePropertiesDisableSidebarLink'] ) ) {
			return;
		}
		if ( !defined( 'SMW_VERSION' ) ) {
			return;
		}

		$specialpage_title = SpecialPage::getTitleFor( 'EditSemantic' );
		$bar[ wfMessage( 'pageproperties' )->text() ][] = [
			'text'   => wfMessage( 'pageproperties-new-article' )->text(),
			'class'   => "pageproperties-new-article",
			'href'   => $specialpage_title->getLocalURL()
		];

		$user = $skin->getUser();

		$title = $skin->getTitle();
		$specialpage_title = SpecialPage::getTitleFor( 'PageProperties' );
		if ( strpos( $title->getFullText(), $specialpage_title->getFullText() ) === 0 ) {
			$par = str_replace( $specialpage_title->getFullText() . '/', '', $title->getFullText() );
			$title = Title::newFromText( $par, NS_MAIN );
		}
		if ( $user->isAllowed( 'pageproperties-caneditpageproperties' ) ) {
			if ( \PageProperties::isKnownArticle( $title ) ) {
				$bar[ wfMessage( 'pageproperties' )->text() ][] = [
					'text'   => wfMessage( 'pageproperties-label' )->text(),
					'href'   => $specialpage_title->getLocalURL() . '/' . wfEscapeWikiText( $title->getPrefixedURL() )
				];
			}
		}

		if ( $user->isAllowed( 'pageproperties-canmanagesemanticproperties' ) ) {
			$specialpage_title = SpecialPage::getTitleFor( 'ManageProperties' );
			$bar[ wfMessage( 'pageproperties' )->text() ][] = [
				'text'   => wfMessage( 'manageproperties-label' )->text(),
				'href'   => $specialpage_title->getLocalURL()
			];
		}

		$forms = \PageProperties::getPagesWithPrefix( null, NS_PAGEPROPERTIESFORM );
		$specialpage_title = SpecialPage::getTitleFor( 'EditSemantic' );
		// $specialpage_title = SpecialPage::getTitleFor( 'PagePropertiesFormEdit' );
		foreach ( $forms as $value ) {
			$bar[ wfMessage( 'pageproperties-forms-label' )->text() ][] = [
				'text'   => $value->getText(),
				'href'   => wfAppendQuery( $specialpage_title->getLocalURL(), 'form=' . urlencode( $value->getDbKey() ) )
			];
		}
	}

	/**
	 * @param Skin $skin
	 * @param array &$sidebar
	 * @return void
	 */
	public static function onSidebarBeforeOutput( $skin, &$sidebar ) {
		if ( !empty( $GLOBALS['wgPagePropertiesDisableSidebarLink'] ) ) {
			return;
		}
		if ( defined( 'SMW_VERSION' ) ) {
			return;
		}
		$title = $skin->getTitle();
		if ( !$title->canExist() ) {
			return;
		}

		$user = $skin->getUser();
		$is_allowed = ( $user->isAllowed( 'pageproperties-caneditpageproperties' ) );

		if ( !$is_allowed ) {
			return;
		}

		$specialpage_title = SpecialPage::getTitleFor( 'PageProperties' );

		if ( strpos( $title->getFullText(), $specialpage_title->getFullText() ) === 0 ) {
			$par = str_replace( $specialpage_title->getFullText() . '/', '', $title->getFullText() );
			$title = Title::newFromText( $par, NS_MAIN );

			if ( !$title || !$title->isKnown() ) {
				return;
			}
		}

		$sidebar['TOOLBOX'][] = [
			'text'   => wfMessage( 'pageproperties-label' )->text(),
			'href'   => $specialpage = $specialpage_title->getLocalURL() . '/' . $title->getFullText()
		];
	}

	/**
	 * @param SkinTemplate $skinTemplate
	 * @param array &$links
	 * @return void
	 */
	public static function onSkinTemplateNavigation( SkinTemplate $skinTemplate, array &$links ) {
		$user = $skinTemplate->getUser();
		$title = $skinTemplate->getTitle();

		if ( !$title->canExist() ) {
			return;
		}

		$errors = [];

		// check if user can edit the page
		if ( defined( 'SMW_VERSION' ) && \PageProperties::checkWritePermissions( $user, $title, $errors ) ) {

			if ( $user->isAllowed( 'pageproperties-canmanagesemanticproperties' )
				|| $user->isAllowed( 'pageproperties-caneditsemanticproperties' )
				|| $user->isAllowed( 'pageproperties-canaddsingleproperties' )
				|| $user->isAllowed( 'pageproperties-cancomposeforms' ) ) {

				$link = [
					'class' => ( $skinTemplate->getRequest()->getVal( 'action' ) === 'editsemantic' ? 'selected' : '' ),
					'text' => wfMessage( 'pageproperties-editsemantic-label' )->text(),
					'href' => $title->getLocalURL( 'action=editsemantic' )
				];

				$keys = array_keys( $links['views'] );
				$pos = array_search( 'edit', $keys );

				$links['views'] = array_intersect_key( $links['views'], array_flip( array_slice( $keys, 0, $pos + 1 ) ) )
					+ [ 'semantic_edit' => $link ] + array_intersect_key( $links['views'], array_flip( array_slice( $keys, $pos + 1 ) ) );
			}
		}

		if ( !empty( $GLOBALS['wgPagePropertiesDisableNavigationLink'] ) ) {
			return;
		}

		if ( !empty( $GLOBALS['wgPagePropertiesShowSlotsNavigation'] ) ) {
			$slots = \PageProperties::getSlots( $title );
			if ( $slots ) {
				$namespaces = $links['namespaces'];
				$links['namespaces'] = [];
				$selectedSlot = ( isset( $_GET['slot'] ) ? $_GET['slot'] : null );

				foreach ( $slots as $role => $slot ) {
					$selected = ( ( !$selectedSlot && $role === MediaWiki\Revision\SlotRecord::MAIN ) || $role === $selectedSlot );

					$links['namespaces'][] = [
						'text' => ( $role === 'main' ? $namespaces[ array_key_first( $namespaces ) ]['text'] : wfMessage( 'pageproperties-slot-label-' . $role )->text() ),
						'class' => ( $selected ? 'selected' : '' ),
						'context' => 'subject',
						'exists' => 1,
						'primary' => 1,
						// @see includes/skins/SkinTemplate.php -> buildContentNavigationUrls()
						'id' => 'ca-nstab-' . $role,
						'href' => ( $role !== MediaWiki\Revision\SlotRecord::MAIN ? wfAppendQuery( $title->getLocalURL(), 'slot=' . $role ) : $title->getLocalURL() ),
					];
				}

				foreach ( $namespaces as $value ) {
					if ( $value['context'] !== 'subject' ) {
						$links['namespaces'][] = $value;
					}
				}
			}
		}

		if ( !defined( 'SMW_VERSION' ) && $title->getNamespace() === NS_CATEGORY ) {
			return;
		}

		if ( !\PageProperties::isKnownArticle( $title ) ) {
			return;
		}

		// *** this only makes sense if we can edit a specific
		// property in ManageProperties
		// if ( $user->isAllowed( 'pageproperties-canmanagesemanticproperties' ) &&
		// 	( ( defined( 'SMW_VERSION' ) && $title->getNamespace() === SMW_NS_PROPERTY ) || $title->getNamespace() === NS_CATEGORY ) ) {
		// 	$title_ = SpecialPage::getTitleFor( 'ManageProperties' );
		// 	$links[ 'actions' ][] = [
		// 		'text' => wfMessage( 'manageproperties-label' )->text(), 'href' => $title_->getLocalURL() . '/' . wfEscapeWikiText( $title->getPrefixedURL() )
		// 	];
		//
		// 	return;
		// }

		if ( $user->isAllowed( 'pageproperties-caneditpageproperties' ) ) {
			$title_ = SpecialPage::getTitleFor( 'PageProperties' );
			$links[ 'actions' ][] = [
				'text' => wfMessage( 'properties-navigation' )->text(), 'href' => $title_->getLocalURL() . '/' . wfEscapeWikiText( $title->getPrefixedURL() )
			];
		}
	}

	/**
	 * @todo replace the editor for alternate slots
	 * @param EditPage $editPage
	 * @param OutputPage $output
	 * @return void
	 */
	public static function onEditPageshowEditForminitial( EditPage $editPage, OutputPage $output ) {
	}

	/**
	 * @param OutputPage $outputPage
	 * @param Skin $skin
	 * @return void
	 */
	public static function onBeforePageDisplay( OutputPage $outputPage, Skin $skin ) {
		// @todo use resources messages and replace dynamictable.js (use OOUI widgets instead)
		$outputPage->addJsConfigVars( [
			'pageproperties-js-alert-1' => wfMessage( 'pageproperties-js-alert-1' )->text(),
			'pageproperties-js-alert-2' => wfMessage( 'pageproperties-js-alert-2' )->text()
		] );

		$outputPage->addHeadItem( 'pageproperties_content_model', '<style>.pageproperties-content-model-text{ font-family: monospace; white-space:pre-wrap; word-wrap:break-word; }</style>' );

		// *** this is required only for CategoriesMultiselectWidget, now removed
		// $outputPage->addHeadItem( 'page_properties_categories_input','<style> .mw-widgets-tagMultiselectWidget-multilineTextInputWidget{ display: none; } </style>' );

		// *** the rationale for this is that for background processes
		// is not a good practice to display an indicator
		if ( empty( $GLOBALS['wgPagePropertiesKeepAnnoyingSMWVerticalBarLoader'] ) ) {
			$outputPage->addScript( Html::inlineScript( 'var elements = document.getElementsByClassName( "smw-indicator-vertical-bar-loader" ); if ( elements.length ) { elements[0].remove(); }' ) );
		}

		$title = $outputPage->getTitle();
		if ( $outputPage->isArticle() && \PageProperties::isKnownArticle( $title ) ) {
			if ( empty( $GLOBALS['wgPagePropertiesDisableJsonLD'] ) ) {
				\PageProperties::setJsonLD( $title, $outputPage );
			}
			\PageProperties::setMetaAndTitle( $title, $outputPage );
		}
	}

	/**
	 * @param SMWStore $store
	 * @param SemanticData $semanticData
	 * @return void
	 */
	public static function onSMWStoreBeforeDataUpdateComplete( $store, $semanticData ) {
		\PageProperties::updateSemanticData( $semanticData, 'onSMWStoreBeforeDataUpdateComplete' );
	}

	/**
	 * *** open external links in a new window
	 * @param string &$url
	 * @param string &$text
	 * @param string &$link
	 * @param array &$attribs
	 * @param string $linktype
	 * @return void
	 */
	public static function onLinkerMakeExternalLink( &$url, &$text, &$link, &$attribs, $linktype ) {
		if ( !empty( $GLOBALS['wgPagePropertiesOpenExternalLinksInNewTab'] )
			&& !array_key_exists( 'target', $attribs ) ) {
			$attribs['target'] = '_blank';
		}
	}

}
