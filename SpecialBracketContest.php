<?php

class SpecialBracketContest extends SpecialPage {

	protected $controller;

	function __construct() {
		parent::__construct( 'BracketContest' );

		$this->controller = new Controller();
	}

	function getGroupName() {
		return 'liquipedia';
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$out = $this->getOutput();
		$this->setHeaders();

		if ( $request->getVal( 'module' ) == 'ranking' && is_numeric( $request->getVal( 'id' ) ) ) {
			self::executeRanking();
		} else if ( $request->getVal( 'module' ) == 'user' ) {
			self::executeUser();
		} else {
			self::executeBase();
		}
	}

	function executeBase() {
		$out = $this->getOutput();

		$out->addModules( 'ext.bracketContest.BasePage' );
		$out->setPageTitle( wfMessage( 'bracketcontest-base-page-title' ) );

		$contests = $this->controller->getContests( [ 'startdate' => 'DESC', 'id' => 'DESC' ] );

		$lastThreeContests = array_pad( array_slice( $contests, 0, 3 ), 3, null );
		self::addLastThreeContentsBanners( $lastThreeContests );

		self::addContestsTable( $contests );
	}

	function executeRanking() {
		$request = $this->getRequest();
		$out = $this->getOutput();

		$out->addModules( 'ext.bracketContest.RankingPage' );
		$out->setSubtitle( Linker::link( $this->getTitle(), wfMessage( 'bracketcontest-all-contests' ) ) );

		$id = $request->getVal( 'id' );
		$contest = $this->controller->getContest( $id );
		if ( $contest->id === null ) {
			$out->addHTML( wfMessage( 'bracketcontest-contest-not-found' ) );
			return;
		}

		$out->setPageTitle( $contest->title );

		// Pager 1
		$this->addPager();

		// Table
		$rankingsTable = [
			'header' => [
				[ 'html' => '#', 'attributes' => [ 'class' => 'filter-false' ] ],
				[ 'html' => wfMessage( 'bracketcontest-name' ), 'attributes' => [ 'width' => '160' ] ],
				[ 'html' => wfMessage( 'bracketcontest-points' ), 'attributes' => [ 'class' => 'filter-false' ] ],
				[ 'html' => wfMessage( 'bracketcontest-maxpossiblepoints' ), 'attributes' => [ 'class' => 'filter-false' ] ],
				[ 'html' => wfMessage( 'bracketcontest-submission' ), 'attributes' => [ 'class' => 'filter-false' ] ]
			],
			'rows' => [],
			'attributes' => [
				'id' => 'ranking',
				'style' => 'width:350px;'
			]
		];
		$participants = $this->controller->getParticipants( $id );
		$ranking = 0;
		$index = 0;
		$points = null;
		foreach ( $participants as $participant ) {
			$index++;
			if ( $participant->points != $points ) {
				$ranking = $index;
				$points = $participant->points;
			}

			$rankingsTable[ 'rows' ][] = [
				[ 'html' => $ranking ],
				[ 'html' => Linker::link( $this->getTitle(), $participant->name, [], [ 'module' => 'user', 'name' => $participant->name ] ) ],
				[ 'html' => $participant->points ],
				[ 'html' => $participant->maxpossiblepoints ],
				[ 'html' => self::getSubmissionLinkFromURL( $participant->link ) ]
			];
		}

		$out->addHTML( self::buildTableHtml( $rankingsTable ) );

		// Pager 2
		$this->addPager();
	}

	function executeUser() {
		$request = $this->getRequest();
		$out = $this->getOutput();

		$out->addModules( 'ext.bracketContest.UserPage' );

		$name = $request->getVal( 'name' );
		$participant = $this->controller->getParticipantByName( $name );
		if ( $participant->id === null ) {
			$out->addHTML( wfMessage( 'bracketcontest-participant-not-found' ) );
			return;
		}

		$out->setPageTitle( wfMessage( 'bracketcontest-user-submissions-title' ) );

		$submissions = $this->controller->getSubmissions( $participant->id );

		$nt = Title::makeTitleSafe( NS_USER, $name );
		$out->setSubtitle( wfMessage( 'bracketcontest-user-submissions-subtitle-start', $name )->parse() . " &bull; " . Linker::link( $this->getTitle(), wfMessage( 'bracketcontest-all-contests' ) ) );

		// Table
		$submissionsTable = [
			'header' => [
				[ 'html' => wfMessage( 'bracketcontest-title' ), 'attributes' => [ 'width' => '300' ] ],
				[ 'html' => wfMessage( 'bracketcontest-game' ) ],
				[ 'html' => wfMessage( 'bracketcontest-points' ) ],
				[ 'html' => wfMessage( 'bracketcontest-submission' ) ]
			],
			'rows' => [],
			'attributes' => [
				'id' => 'submissions',
				'style' => 'width:600px;'
			]
		];
		foreach ( $submissions as $submission ) {
			$submissionsTable[ 'rows' ][] = [
				[ 'html' => Linker::link( $this->getTitle(), $submission[ 'title' ], [], [ 'module' => 'ranking', 'id' => $submission[ 'id' ] ] ) ],
				[ 'html' => $submission[ 'game' ] ],
				[ 'html' => $submission[ 'points' ] ],
				[ 'html' => self::getSubmissionLinkFromURL( $submission[ 'link' ] ) ]
			];
		}

		$out->addHTML( self::buildTableHtml( $submissionsTable ) );
	}

	function addLastThreeContentsBanners( $lastThreeContests ) {
		$out = $this->getOutput();

		$output = '<div class="bct-last-three-contests">';

		foreach ( $lastThreeContests as $contest ) {
			$link = '';

			if ( $contest !== null ) {
				$imageTitle = Title::makeTitleSafe( NS_FILE, 'BC' . $contest->id . '.jpg' );
				$imageFile = wfFindFile( $imageTitle );
				if ( is_object( $imageFile ) && $imageFile->exists() ) {
					$link = '[[File:BC' . $contest->id . '.jpg'
						. '|267x178px'
						. '|' . $contest->title
						. '|link={{FULLURL:Special:BracketContest|module=ranking&id=' . $contest->id . '}}'
						. ']]';
				} else {
					$link = '<div class="plainlinks">[{{FULLURL:Special:BracketContest|module=ranking&id=' . $contest->id . '}} ' . $contest->title . ']</div>';
				}
			}

			$output .= '<div class="bct-banner">' . $link . '</div>';
		}

		$output .= '</div>';

		$out->addWikitext( $output );
	}

	function addContestsTable( $contests ) {
		$out = $this->getOutput();

		$contestsTable = [
			'header' => [
				[ 'wikitext' => wfMessage( 'bracketcontest-title' ), 'attributes' => [ 'style' => 'width:250px' ] ],
				[ 'wikitext' => wfMessage( 'bracketcontest-game' ) ],
				[ 'wikitext' => wfMessage( 'bracketcontest-start' ) ],
				[ 'wikitext' => wfMessage( 'bracketcontest-submissions-before' ) ],
				[ 'wikitext' => wfMessage( 'bracketcontest-end' ) ],
			],
			'rows' => [],
			'attributes' => [
				'id' => 'contest',
				'style' => 'width:850px;'
			]
		];

		foreach ( $contests as $contest ) {
			$imageTitle = Title::makeTitleSafe( NS_FILE, 'BC' . $contest->id . '_small.png' );
			$imageFile = wfFindFile( $imageTitle );
			if ( !is_object( $imageFile ) || !$imageFile->exists() ) {
				$image = '[[File:LeaguesPlaceholder.png'
					. '|25x25px'
					. '|' . $contest->title
					. '|link={{FULLURL:Special:BracketContest|module=ranking&id=' . $contest->id . '}}'
					. ']]';
			} else {
				$image = '[[File:BC' . $contest->id . '_small.png'
					. '|25x25px'
					. '|' . $contest->title
					. '|link={{FULLURL:Special:BracketContest|module=ranking&id=' . $contest->id . '}}'
					. ']]';
			}
			$contestsTable[ 'rows' ][] = [
				[
					'wikitext' => $image . '&nbsp;&nbsp;'
					. '<span class="plainlinks">[{{FULLURL:Special:BracketContest|module=ranking&id=' . $contest->id . '}} ' . $contest->title . ']</span>'
				],
				[
					'wikitext' => $contest->game,
					'attributes' => [ 'class' => 'game ' . strtolower( str_replace( [ ': ', ':', ' ' ], [ '-', '-', '-' ], $contest->game ) ) ]
				],
				[
					'wikitext' => $contest->startdate ? date_format( date_create( $contest->startdate ), 'Y-m-d H:i' ) : ''
				],
				[
					'wikitext' => $contest->submissiondate ? date_format( date_create( $contest->submissiondate ), 'Y-m-d H:i' ) : ''
				],
				[
					'wikitext' => $contest->enddate ? date_format( date_create( $contest->enddate ), 'Y-m-d H:i' ) : ''
				]
			];
		}

		$out->addWikitext( self::buildTableWikitext( $contestsTable ) );
	}

	function getSubmissionLinkFromURL( $url ) {
		$url = str_replace( '&oldid=', '?oldid=', $url );
		return Html::element( 'a', [ 'href' => $url, 'title' => 'link' ], wfMessage( 'bracketcontest-link' ) );
	}

	function addPager() {
		$out = $this->getOutput();

		$html = <<<EOT
	<div id="pager" class="pager">
		<form>
		<img class="first"/>
		<img class="prev"/>
		<span class="pagedisplay"></span>
		<img class="next"/>
		<img class="last"/>
		<select class="pagesize">
			<option value="10">10</option>
			<option value="20">20</option>
			<option value="30">30</option>
			<option value="40">40</option>
			<option selected="selected" value="50">50</option>
			<option value="70">70</option>
			<option value="100">100</option>
		</select>
		</form>
	</div>

EOT;
		$out->addHTML( $html );
	}

	function buildTableWikitext( $variables ) {
		$header = $variables[ 'header' ];
		$rows = $variables[ 'rows' ];
		$attributes = $variables[ 'attributes' ];

		$output = '{|';

		if ( count( $attributes ) ) {
			foreach ( $attributes as $key => $value ) {
				$output .= ($key . '="' . $value . '" ');
			}
		}

		$output .= "\n";

		// Header
		if ( count( $header ) ) {
			$output .= "|-\n";
			foreach ( $header as $cell ) {
				$cellAttributes = isset( $cell[ 'attributes' ] ) ? $cell[ 'attributes' ] : [];
				$output .= '!';
				if ( count( $cellAttributes ) ) {
					foreach ( $cellAttributes as $key => $value ) {
						$output .= ($key . '="' . $value . '" ');
					}
				}
				$output .= '|' . $cell[ 'wikitext' ] . "\n";
			}
		}

		// Rows
		if ( count( $rows ) ) {
			foreach ( $rows as $row ) {
				$output .= "|-\n";
				foreach ( $row as $cell ) {
					$cellAttributes = isset( $cell[ 'attributes' ] ) ? $cell[ 'attributes' ] : [];
					$output .= '|';
					if ( count( $cellAttributes ) ) {
						foreach ( $cellAttributes as $key => $value ) {
							$output .= ($key . '="' . $value . '" ');
						}
					}
					$output .= '|' . $cell[ 'wikitext' ] . "\n";
				}
			}
		}

		// Table
		$output .= '|}' . "\n";

		return $output;
	}

	function buildTableHtml( $variables ) {
		$header = $variables[ 'header' ];
		$rows = $variables[ 'rows' ];
		$attributes = $variables[ 'attributes' ];

		$innerTableHTML = "\n";

		// Header
		if ( count( $header ) ) {
			$innerTableHTML .= "  <thead>\n";
			$innerTableHTML .= "    <tr>\n";
			foreach ( $header as $cell ) {
				$cellAttributes = isset( $cell[ 'attributes' ] ) ? $cell[ 'attributes' ] : null;
				$innerTableHTML .= '      ' . Html::element( 'th', $cellAttributes, $cell[ 'html' ] ) . "\n";
			}
			$innerTableHTML .= "    </tr>\n";
			$innerTableHTML .= "  </thead>\n";
		}

		// Rows
		$innerTableHTML .= "  <tbody>\n";
		if ( count( $rows ) ) {
			foreach ( $rows as $row ) {
				$innerTableHTML .= "    <tr>\n";
				foreach ( $row as $cell ) {
					$cellAttributes = isset( $cell[ 'attributes' ] ) ? $cell[ 'attributes' ] : null;
					$innerTableHTML .= '      ' . Html::rawElement( 'td', $cellAttributes, $cell[ 'html' ] ) . "\n";
				}
				$innerTableHTML .= "    </tr>\n";
			}
		}
		$innerTableHTML .= "  </tbody>\n";

		// Table
		$output = Html::rawElement( 'table', $attributes, $innerTableHTML
		);

		return $output;
	}

}
