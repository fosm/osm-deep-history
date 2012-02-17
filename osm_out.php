<?

function color($prev, $curr) {
  if($prev == "----" and $curr == "----") {
    return "notpresent";
  } else if($prev == "----" and $curr != "----") {
    return "new";
  } else if($prev != "----" and $curr != "----") {
    if($prev == $curr) {
      return "unchanged";
    } else {
      return "changed";
    }
  } else if($prev != "----" and $curr == "----") {
    return "removed";
  }
}

function userAgreed($uid) {
  if($uid >= 286582) {
    return 'agreed';
  } else if(findInFile('users_disagreed.txt', $uid)) {
    return 'disagreed';
  } else if(findInFile('users_agreed.txt', $uid)) {
    return 'agreed';
  } else {
    return 'nodecision';
  }
}

function changesetAgreed($changsetId) {
  if(findInFile('anon_changesets_agreed.txt', $changesetId)) {
    return 'agreed';
  } else {
    return 'nodecision';
  }
}

function findInFile($file, $needle) {
  $data = file($file);

  $top = sizeof($data) - 1;
  $bot = 0;

  while($top >= $bot) {
    $p = floor(($top + $bot) / 2);
    if((int) $data[$p] < $needle) $bot = $p + 1;
    else if((int) $data[$p] > $needle) $top = $p - 1;
    else return TRUE;
  }

  return FALSE;
}

function refLine($ways, $ref) {
  $ret = "<tr>";
  $previousVal = "----";
  $i = 0;
  
  $ret .= "<td style='background:#ccc;'><img src='node.png'/><a href='node.php?id=$ref'>$ref</a></td>";

  foreach ($ways as $way) {
    $refs = $way['refs'];
    if(!array_key_exists($ref, $refs)) {
      $currentVal = "----";
    } else {
      $currentVal = $ref[$ref];
    }
    $class = color($previousVal, $currentVal);
    $ret .= "<td class='$class'>&nbsp;</td>";
    $previousVal = $currentVal;
  }
  $ret .= "</tr>\n";
  return $ret;
}

function memberLine($relations, $type, $ref) {
  $ret = "<tr>";
  $previousVal = "----";
  $i = 0;

  $ret .= "<td style='background:#ccc;'><img src='$type.png'/><a href='$type.php?id=$ref'>$ref</a></td>";

  foreach ($relations as $relation) {
    $members = $relation['members'];
    if(!array_key_exists("$type,$ref", $members)) {
      $currentVal = "----";
      $class = color($previousVal, $currentVal);
      $ret .= "<td class='$class'>&nbsp;</td>";
    } else {
      $currentVal = $members["$type,$ref"];
      $class = color($previousVal, $currentVal);
      if($currentVal == "") {
        $ret .= "<td class='$class empty'>(empty)</td>";
      } else {
        $ret .= "<td class='$class'>$currentVal</td>";
      }
    }
    $previousVal = $currentVal;
  }
  $ret .= "</tr>\n";
  return $ret;
}

function tagLine($ways, $key, $title) {
  $ret = "<tr>";
  $previousVal = "----";
  $i = 0;
  
  $ret .= "<td style='background:#ccc;'><img src='tag.png'/>$title</td>";

  foreach ($ways as $way) {
    $tags = $way['tags'];
    if(!array_key_exists($key, $tags)) {
      $currentVal = "----";
      $class = color($previousVal, $currentVal);
      $ret .= "<td class='$class'>&nbsp;</td>";
    } else {
      $currentVal = $way['tags'][$key];
      $shortVal = $currentVal;
      $class = color($previousVal, $currentVal);
      if(strlen($currentVal) > 20) {
        $shortVal = substr($currentVal, 0, 20) . "&#8230;";
        $displayVal = str_replace(' ', '&#9251;', $currentVal);
        $shortVal = str_replace(' ', '&#9251;', $shortVal);
        $ret .= "<td class='$class'><abbr title='$displayVal'>$shortVal</abbr></td>";
      } else {
        $displayVal = str_replace(' ', '&#9251;', $currentVal);
        $ret .= "<td class='$class'>$displayVal</td>";
      }
    }
    $previousVal = $currentVal;
  }
  $ret .= "</tr>\n";
  return $ret;
}

function timeLine($ways) {
  $ret = "<tr>";
  $ret .= "<td style='background:#ccc;'>Time</td>";
  foreach ($ways as $way) {
    $currentVal = $way['time'];
    $ret .= "<td style='background:#ccc;'>$currentVal</td>";
  }
  $ret .= "</tr>\n";
  return $ret;
}

function wayLine($ways, $val, $useColor=true, $title, $link=Null) {
  $ret = "<tr>";
  $previousVal = "----";
  $ret .= "<td style='background:#ccc;'>$title</td>";
  foreach ($ways as $way) {
    $currentVal = $way[$val];
    $class = color($previousVal, $currentVal);
    if($link)
      if(($val == "changeset") && ($currentVal >= 1000000000))
        $ret .= "<td class='$class'><a href='http://api.fosm.org/api/0.6/changeset/$currentVal'>$currentVal</a></td>";
      else
        $ret .= "<td class='$class'><a href='$link$currentVal'>$currentVal</a></td>";
    else
      $ret .= "<td class='$class'>$currentVal</td>";
    $previousVal = $currentVal;
  }
  $ret .= "</tr>\n";
  return $ret;
}

function licenseLine($ways) {
  $ret = "<tr>";
  $ret .= "<td style='background:#ccc;'>Status</td>";
  foreach ($ways as $way) {
    if($way['uid']) {
      $class = userAgreed($way['uid']);
    } else {
      $class = changesetAgreed($way['changeset']);
    }
    $ret .= "<td class='$class empty'>$class</td>";
  }
  $ret .= "</tr>\n";
  return $ret;
}

function printCopyright() {
  print "<!--
  This page contains data copyright OpenStreetMap and FOSM Contributors released
  under the Creative Commons Attribution-ShareAlike 2.0 license, available at
  http://creativecommons.org/licenses/by-sa/2.0/

  Hence this document is also released under the same license.

  For the full attribution details of this data please refer to both
  http://www.openstreetmap.org/
  http://www.fosm.org/

  The code which generated this document is released under the Apache License
  Version 2.0, http://www.apache.org/licenses/LICENSE-2.0

  The code is available at
  https://github.com/andrewharvey/osm-deep-history/tree/fosm
-->
";
}

$UA_STRING = "curl/deep_history_viewer";

function getObject($type, $id) {
  $output = "";

  // Always try the fosm api first (because if an object was created in osm but
  // subsequently edited in fosm then it will still have the osm id)
  $url = "http://api.fosm.org/api/0.6/$type/$id/history";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERAGENT, $UA_STRING);
  $output = curl_exec($ch);

  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  // try OSM if gone from api.fosm.org
  // try OSM if not found in api.fosm.org
  if(($http_code == 410) || ($http_code == 404) || ($http_code == 307)) {
    if ($id >= 1000000000000) {
        print "Error retrieving history\n";
        print "URL: $url\n";
        print "Response code: $http_code\n";
        if (($http_code == 410) || ($http_code == 307))
          print "I would normally now look at www.osm.org but the object id is too large.\n";
        exit;
    }else{
      $url = "http://www.openstreetmap.org/api/0.6/$type/$id/history";

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_USERAGENT, $UA_STRING);
      $output = curl_exec($ch);

      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if($http_code != 200) {
        print "Error retrieving history\n";
        print "URL: $url\n";
        print "Response code: $http_code\n";
        exit;
      }
    }
  }else if($http_code != 200) {
    print "Error retrieving history\n";
    print "URL: $url\n";
    print "Response code: $http_code\n";
    exit;
  }

  return $output;
}
?>

