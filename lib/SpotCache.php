<?php

class SpotCache {
	protected $_db;

	const SpotImage	= 1;
	const SpotNzb	= 2;
	const Web		= 3;

	function __construct(SpotDb $db) {
		$this->_db = $db;
	} # ctor

	function getCache($resourceid, $cachetype) {
		SpotTiming::start(__FUNCTION__);
		$data = $this->_db->getCache($resourceid, $cachetype);
		SpotTiming::stop(__FUNCTION__, array($data));

		if ($data) {
			return $data;
		} else {
			return false;
		} # else
	} # getCache

	function saveCache($resourceid, $cachetype, $metadata, $content, $compress=false) {
		SpotTiming::start(__FUNCTION__);
		$this->_db->saveCache($resourceid, $cachetype, $metadata, $content, $compress);
		SpotTiming::stop(__FUNCTION__, array($resourceid, $cachetype, $metadata, $content, $compress));
	} # saveCache

	function updateCacheStamp($resourceid, $cachetype) {
		SpotTiming::start(__FUNCTION__);
		$this->_db->updateCacheStamp($resourceid, $cachetype);
		SpotTiming::stop(__FUNCTION__, array($resourceid, $cachetype));
	} # updateCacheStamp
	
} # class SpotCache