<?php
  function gf_db_connect($server = DB_SERVER, $username = DB_SERVER_USERNAME, $password = DB_SERVER_PASSWORD, $database = DB_DATABASE, $link = 'db_link') {
    global $$link;

    if (USE_PCONNECT == 'true') {
      $$link = mysql_pconnect($server, $username, $password);
    } else {
      $$link = mysql_connect($server, $username, $password);
    }

    if ($$link) {
		if (floatval(mysql_get_server_info($$link)) >= 4.1 ) {
			@gf_db_query("SET NAMES 'utf8'");
		}
		mysql_select_db($database);
	}

    return $$link;
  }

  function gf_db_close($link = 'db_link') {
    global $$link;

    return mysql_close($$link);
  }

  function gf_db_error($query, $errno, $error) { 
	if (MYSITE_IS_DEBUG == 'true') {
		die('<font color="#000000"><b>' . $errno . ' - ' . $error . '<br><br>' . $query . '<br><br><small><font color="#ff0000"></font></small><br><br></b></font>');
	} else {
		die('<font color="#000000"><b>Database error,Session halted.</b></font>');
	}
  }

  function gf_db_query($query, $link = 'db_link') {
    global $$link;

    $result = mysql_query($query, $$link) or gf_db_error($query, mysql_errno(), mysql_error());

    return $result;
  }

  function gf_db_perform($table, $data, $action = 'insert', $parameters = '', $link = 'db_link') {
    reset($data);
    if ($action == 'insert') {
      $query = 'insert into ' . $table . ' (';
      while (list($columns, ) = each($data)) {
        $query .= $columns . ', ';
      }
      $query = substr($query, 0, -2) . ') values (';
      reset($data);
      while (list(, $value) = each($data)) {
        switch ((string)$value) {
          case 'now()':
            $query .= 'now(), ';
            break;
          case 'null':
            $query .= 'null, ';
            break;
          default:
            $query .= '\'' . gf_db_input($value) . '\', ';
            break;
        }
      }
      $query = substr($query, 0, -2) . ')';
    } elseif ($action == 'update') {
      $query = 'update ' . $table . ' set ';
      while (list($columns, $value) = each($data)) {
        switch ((string)$value) {
          case 'now()':
            $query .= $columns . ' = now(), ';
            break;
          case 'null':
            $query .= $columns .= ' = null, ';
            break;
          default:
            $query .= $columns . ' = \'' . gf_db_input($value) . '\', ';
            break;
        }
      }
      $query = substr($query, 0, -2) . ' where ' . $parameters;
    }

    return gf_db_query($query, $link);
  }

  function gf_db_fetch_array($db_query) {
    return mysql_fetch_array($db_query, MYSQL_ASSOC);
  }

  function gf_db_num_rows($db_query) {
    return mysql_num_rows($db_query);
  }

  function gf_db_data_seek($db_query, $row_number) {
    return mysql_data_seek($db_query, $row_number);
  }

  function gf_db_insert_id() {
    return mysql_insert_id();
  }

  function gf_db_free_result($db_query) {
    return mysql_free_result($db_query);
  }

  function gf_db_fetch_fields($db_query) {
    return mysql_fetch_field($db_query);
  }

  function gf_db_output($string) {
    return stripslashes($string);
  }

function gf_db_input($string, $comp=false) {
	$string = gf_db_output($string);
	$string = addslashes($string);
	
	if ($comp) {
		$string = str_replace("_","\_",$string);
		$string = str_replace("%","\%",$string);
	}
	return $string;
}

  function gf_db_prepare_input($string) {
    if (is_string($string)) {
      return trim(stripslashes($string));
    } elseif (is_array($string)) {
      reset($string);
      while (list($key, $value) = each($string)) {
        $string[$key] = gf_db_prepare_input($value);
      }
      return $string;
    } else {
      return $string;
    }
  }
?>