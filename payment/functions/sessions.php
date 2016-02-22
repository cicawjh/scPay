<?php
/*
  sessions.php

  http://www.etiusa.net

  Copyright (c) 2003 Etelligence Technologies, Inc.
*/

	if (STORE_SESSIONS == 'mysql') {
		if (!$SESS_LIFE = get_cfg_var('session.gc_maxlifetime')) {
			$SESS_LIFE = 1440;
		}

		function _sess_open($save_path, $session_name) {
			return true;
		}

		function _sess_close() {
			return true;
		}

		function _sess_read($key) {
			$qid = gf_db_query("select value from " . TABLE_SESSIONS . " where sessions_key = '" . $key . "' and expiry > '" . time() . "'");

			$value = gf_db_fetch_array($qid);
			if ($value['value']) {
				return $value['value'];
			}

			return false;
		}

		function _sess_write($key, $val) {
			global $SESS_LIFE;

			$expiry = time() + $SESS_LIFE;
			$value = addslashes($val);

			$qid = gf_db_query("select count(*) as total from " . TABLE_SESSIONS . " where sessions_key = '" . $key . "'");
			$total = gf_db_fetch_array($qid);

			if ($total['total'] > 0) {
				return gf_db_query("update " . TABLE_SESSIONS . " set expiry = '" . $expiry . "', value = '" . $value . "' where sessions_key = '" . $key . "'");
			} else {
				return gf_db_query("insert into " . TABLE_SESSIONS . " values ('" . $key . "', '" . $expiry . "', '" . $value . "')");
			}
		}

		function _sess_destroy($key) {
			return gf_db_query("delete from " . TABLE_SESSIONS . " where sessions_key = '" . $key . "'");
		}

		function _sess_gc($maxlifetime) {
			gf_db_query("delete from " . TABLE_SESSIONS . " where expiry < '" . time() . "'");

			return true;
		}

		session_set_save_handler('_sess_open', '_sess_close', '_sess_read', '_sess_write', '_sess_destroy', '_sess_gc');
	}

	function gf_session_start() {
		return session_start();
	}

	function gf_session_register($variable) {
		return session_register($variable);
	}

	function gf_session_is_registered($variable) {
		return session_is_registered($variable);
	}

	function gf_session_unregister($variable) {
		return session_unregister($variable);
	}

	function gf_session_id($sessid = '') {
		if (!empty($sessid)) {
			return session_id($sessid);
		} else {
			return session_id();
		}
	}

	function gf_session_name($name = '') {
		if (!empty($name)) {
			return session_name($name);
		} else {
			return session_name();
		}
	}

	function gf_session_close() {
		if (function_exists('session_close')) {
			return session_close();
		}
	}

	function gf_session_destroy() {
		return session_destroy();
	}

	function gf_session_save_path($path = '') {
		if (!empty($path)) {
			return session_save_path($path);
		} else {
			return session_save_path();
		}
	}
?>