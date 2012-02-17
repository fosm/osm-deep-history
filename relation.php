<?php

include_once('osm_out.php');

if(!is_numeric($_GET['id'])) {
  exit;
}

$id = $_GET['id'];

$url = "http://api.fosm.org/api/0.6/relation/$id/history";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "curl/deep_history_viewer");
$output = curl_exec($ch);

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// try OSM if gone from api.fosm.org
if($http_code == 410) {
  $url = "http://www.openstreetmap.org/api/0.6/relation/$id/history";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERAGENT, "curl/deep_history_viewer");
  $output = curl_exec($ch);

  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if($http_code != 200) {
    print "Error retrieving history from www.osm.org: $http_code";
    exit;
  }
}else if($http_code != 200) {
  print "Error retrieving history from api.fosm.org: $http_code";
  exit;
}

$xml = simplexml_load_string($output);

$relations = array();
$tag_keys = array();
$relation_refs = array();

// keep track of min/max versions in this /history call as fosm may not contain
// the full history
$min_version = 0;
$max_version = 0;
if ($xml->way > 0) {
  $min_version = $xml->way[0]->attributes()->version;
  $max_version = $min_version;
}

foreach ($xml->relation as $way_xml) {
  $version = (integer) $way_xml->attributes()->version;
  if ($version < $min_version)
    $min_version = $version;
  if ($version > $max_version)
    $max_version = $version;
  $way['changeset'] = (integer) $way_xml->attributes()->changeset;
  $way['user'] = (string) $way_xml->attributes()->user;
  $way['uid'] = (integer) $way_xml->attributes()->uid;
  $way['time'] = (string) $way_xml->attributes()->timestamp;

  $tags = array();
  foreach ($way_xml->tag as $tag_xml) {
    $k = (string) $tag_xml->attributes()->k;
    $v = (string) $tag_xml->attributes()->v;
    $tags[$k] = $v;
    $tag_keys[$k] = true;
  }
  $way['tags'] = $tags;

  $members = array();
  foreach ($way_xml->member as $member_xml) {
    $role = (string) $member_xml->attributes()->role;
    $type = (string) $member_xml->attributes()->type;
    $ref = (string) $member_xml->attributes()->ref;
    $relation_refs["$type,$ref"] = true;
    $members["$type,$ref"] = $role;
  }
  $way['members'] = $members;
  
  $relations[$version] = $way;
}

// fosm didn't return the full history, so now lets get everything from version
// 1 to $min_version - 1 from osm
if ($min_version != 1) {
  $min_version = $max_version;
  $url = "http://www.openstreetmap.org/api/0.6/relation/$id/history";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERAGENT, "curl/deep_history_viewer");
  $output = curl_exec($ch);

  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if($http_code != 200) {
    print "Error retrieving history from www.osm.org: $http_code";
    exit;
  }

  $xml = simplexml_load_string($output);

  foreach ($xml->relation as $way_xml) {
    $version = (integer) $way_xml->attributes()->version;

    // just grab details from versions that are not in the fosm api result, and
    // are lower version numbers than the lowest fosm version number we found
    if ($version < $min_version) {
      $way['changeset'] = (integer) $way_xml->attributes()->changeset;
      $way['user'] = (string) $way_xml->attributes()->user;
      $way['uid'] = (integer) $way_xml->attributes()->uid;
      $way['time'] = (string) $way_xml->attributes()->timestamp;

      $tags = array();
      foreach ($way_xml->tag as $tag_xml) {
        $k = (string) $tag_xml->attributes()->k;
        $v = (string) $tag_xml->attributes()->v;
        $tags[$k] = $v;
        $tag_keys[$k] = true;
      }
      $way['tags'] = $tags;

      $members = array();
      foreach ($way_xml->member as $member_xml) {
        $role = (string) $member_xml->attributes()->role;
        $type = (string) $member_xml->attributes()->type;
        $ref = (string) $member_xml->attributes()->ref;
        $relation_refs["$type,$ref"] = true;
        $members["$type,$ref"] = $role;
      }
      $way['members'] = $members;
      
      $relations[$version] = $way;
    }
  }
}


?>

<head>
  <title>Deep Diff of Relation #<? echo $id ?></title>
  <link rel='stylesheet' type='text/css' media='screen,print' href='style.css'/>
</head>
<body>
  <h3>Relation ID <? echo $id ?></h3>
  <hr />

  <table>
    <tr>
      <td style='background:#aaa;' colspan='<? echo count($relations) + 1 ?>'>Primitive Info</td>
    </tr>

    <? echo timeLine($relations) ?>
    <? echo wayLine($relations, 'changeset', true, "Changeset#", "http://osm.org/browse/changeset/") ?>
    <? echo wayLine($relations, 'user', true, "User", "http://osm.org/user/") ?>
    <tr>
      <td style='background:#aaa;' colspan='<? echo count($relations) + 1 ?>'>Tags</td>
    </tr>
    <?
foreach (array_keys($tag_keys) as $key) {
  print tagLine($relations, $key, $key);
}
    ?>
    <tr>
      <td style='background:#aaa;' colspan='<? echo count($relations) + 1 ?>'>Members</td>
    </tr>
    <?
foreach (array_keys($relation_refs) as $key) {
  list($type, $ref) = split(',', $key);
  print memberLine($relations, $type, $ref);
}
    ?>
  </table>
</body>
