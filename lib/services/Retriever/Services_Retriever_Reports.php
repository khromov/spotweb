<?php
class Services_Retriever_Reports extends Services_Retriever_Base {
		/**
		 * Server is the server array we are expecting to connect to
		 * db - database object
		 */
		function __construct(Dao_Factory $daoFactory, SpotSettings $settings, $debug, $force) {
			parent::__construct($daoFactory, $settings, $debug, $force, false);

			$this->_reportDao = $daoFactory->getSpotReportDao();
			$this->_spotDao = $daoFactory->getSpotDao();
		} # ctor
		
		/*
		 * Returns the status in either xml or text format 
		 */
		function displayStatus($cat, $txt) {
				switch($cat) {
					case 'start'			: echo "Retrieving new reports from server " . $txt . "..." . PHP_EOL; break;
					case 'lastretrieve'		: echo strftime("Last retrieve at %c", $txt) . PHP_EOL; break;
					case 'done'				: echo "Finished retrieving reports." . PHP_EOL . PHP_EOL; break;
					case 'groupmessagecount': echo "Appr. Message count: 	" . $txt . "" . PHP_EOL; break;
					case 'firstmsg'			: echo "First message number:	" . $txt . "" . PHP_EOL; break;
					case 'lastmsg'			: echo "Last message number:	" . $txt . "" . PHP_EOL; break;
					case 'curmsg'			: echo "Current message:	" . $txt . "" . PHP_EOL; break;
					case 'progress'			: echo "Retrieving " . $txt; break;
					case 'loopcount'		: echo ", found " . $txt . " reports"; break;
					case 'timer'			: echo " in " . $txt . " seconds" . PHP_EOL; break;
					case 'totalprocessed'	: echo "Processed a total of " . $txt . " reports" . PHP_EOL; break;
					case 'searchmsgid'		: echo "Looking for articlenumber for messageid" . PHP_EOL; break;
					case 'searchmsgidstatus': echo "Searching from " . $txt . PHP_EOL; break;
					case ''					: echo PHP_EOL; break;
					
					default					: echo $cat . $txt;
				} # switch
		} # displayStatus

		/*
		 * Remove any extraneous reports from the database because we assume
		 * the highest messgeid in the database is the latest on the server.
		 */
		function updateLastRetrieved($highestMessageId) {
			/*
			 * Remove any extraneous reports from the database because we assume
			 * the highest messgeid in the database is the latest on the server.
			 *
			 * If the server is marked as buggy, the last 'x' amount of repors are
			 * always checked so we do not have to do this 
			 */
			if (!$this->_textServer['buggy']) {
				$this->_reportDao->removeExtraReports($highestMessageId);
			} # if
		} # updateLastRetrieved
		
		/*
		 * Actually process the retrieved headers from XOVER
		 */
		function process($hdrList, $curMsg, $endMsg, $timer) {
			$this->displayStatus("progress", ($curMsg) . " till " . ($endMsg));
		
			$signedCount = 0;
			$lastProcessedId = '';
			$reportDbList = array();

			/**
			 * We ask the database to match our messageid's we just retrieved with
			 * the list of id's we have just retrieved from the server
			 */
			$dbIdList = $this->_reportDao->matchReportMessageIds($hdrList);

			/*
			 * We keep a seperate list of messageid's for updating the amount of
			 * reports for each spot.
			 */
			$spotMsgIdList = array();
			
			# Process each header
			foreach($hdrList as $msgid => $msgheader) {
				# Reset timelimit
				set_time_limit(120);			

				# strip the <>'s from the reference
				$reportId = substr($msgheader['Message-ID'], 1, strlen($msgheader['Message-ID']) - 2);

				# Prepare the report to be added to the server when the report isn't in the database yet
				if (!isset($dbIdList[$reportId])) {
					$lastProcessedId = $reportId;
					
					# Extract the keyword and the messageid its reporting about
					$tmpSubject = explode(' ', $msgheader['Subject']);
					if (count($tmpSubject) > 2) {
						$msgheader['keyword'] = $tmpSubject[0];
						$msgheader['References'] = substr($tmpSubject[1], 1, strlen($tmpSubject[1]) - 2);
						$spotMsgIdList[$msgheader['References']] = 1;

						# prepare the spot to be added to the server
						$reportDbList[] = array('messageid' => $reportId,
												 'fromhdr' => utf8_encode($msgheader['From']),
												 'keyword' => utf8_encode($msgheader['keyword']),
												 'nntpref' => $msgheader['References']);
					} # if

					/*
					 * Some buggy NNTP servers give us the same messageid
					 * in once XOVER statement, hence we update the list of
					 * messageid's we already have retrieved and are ready
					 * to be added to the database
					 */
					$dbIdList[$reportId] = 1;
				} # if
			} # foreach

			if (count($hdrList) > 0) {
				$this->displayStatus("loopcount", count($hdrList));
			} else {
				$this->displayStatus("loopcount", 0);
			} # else
			$this->displayStatus("timer", round(microtime(true) - $timer, 2));

			# update the last retrieved article			
			$this->_reportDao->addReportRefs($reportDbList);
			$this->_nntpCfgDao->setMaxArticleid('reports', $endMsg);
			
			# Calculate the amount of reports for a spot
			$this->_spotDao->updateSpotReportCount($spotMsgIdList);
			
			return array('count' => count($hdrList), 'headercount' => count($hdrList), 'lastmsgid' => $lastProcessedId);
		} # process()
		
		/*
		 * returns the name of the group we are expected to retrieve messages from
		 */
		function getGroupName() {
			return array('text' => $this->_settings->get('report_group'),
						 'bin' => '');
		} # getGroupName

		/*
		 * Highest articleid for the implementation in the database
		 */
		function getMaxArticleId() {
			return $this->_nntpCfgDao->getMaxArticleid('reports');
		} # getMaxArticleId

		/*
		 * Returns the highest messageid in the database
		 */
		function getMaxMessageId() {
			return $this->_spotDao->getMaxMessageId('reports');
		} # getMaxMessageId
		
} # Services_Retriever_Reports