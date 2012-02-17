<?php

include_once('osm_out.php');

if(!is_numeric($_GET['id'])) {
  exit;
}

$id = $_GET['id'];

$url = "http://api.fosm.org/api/0.6/node/$id/history";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "curl/deep_history_viewer");
$output = curl_exec($ch);

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// try OSM if gone from api.fosm.org
if($http_code == 410) {
  $url = "http://www.openstreetmap.org/node/0.6/way/$id/history";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERAGENT, "curl/deep_history_viewer");
  $output = curl_exec($ch);

  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if($http_code != 200) {
    print "Error retrieving history";
    print "URL: $url";
    print "Response code: $http_code";
    exit;
  }
}else if($http_code != 200) {
  print "Error retrieving history";
  print "URL: $url";
  print "Response code: $http_code";
  exit;
}

$xml = simplexml_load_string($output);

$nodes = array();
$tag_keys = array();

// keep track of min/max versions in this /history call as fosm may not contain
// the full history
$min_version = 0;
$max_version = 0;
if (count($xml->node) > 0) {
  $min_version = $xml->node[0]->attributes()->version;
  $max_version = $min_version;
}

foreach ($xml->node as $node_xml) {
  $version = (integer) $node_xml->attributes()->version;
  if ($version < $min_version)
    $min_version = $version;
  if ($version > $max_version)
    $max_version = $version;
  $node['version'] = $version;
  $node['lat'] = (double) $node_xml->attributes()->lat;
  $node['lon'] = (double) $node_xml->attributes()->lon;
  $node['changeset'] = (integer) $node_xml->attributes()->changeset;
  $node['user'] = (string) $node_xml->attributes()->user;
  $node['uid'] = (integer) $node_xml->attributes()->uid;
  $node['time'] = (string) $node_xml->attributes()->timestamp;

  $tags = array();
  foreach ($node_xml->tag as $tag_xml) {
    $k = (string) $tag_xml->attributes()->k;
    $v = (string) $tag_xml->attributes()->v;
    $tags[$k] = $v;
    $tag_keys[$k] = true;
  }
  $node['tags'] = $tags;
  
  $nodes[$version] = $node;
}

// fosm didn't return the full history, so now lets get everything from version
// 1 to $min_version - 1 from osm
if ($min_version != 1) {
  $min_version = $max_version;
  $url = "http://www.openstreetmap.org/api/0.6/node/$id/history";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERAGENT, "curl/deep_history_viewer");
  $output = curl_exec($ch);

  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if($http_code != 200) {
    print "Error retrieving history";
    print "URL: $url";
    print "Response code: $http_code";
    exit;
  }

  $xml = simplexml_load_string($output);

  foreach ($xml->node as $node_xml) {
    $version = (integer) $node_xml->attributes()->version;

    // just grab details from versions that are not in the fosm api result, and
    // are lower version numbers than the lowest fosm version number we found
    if ($version < $min_version) {
      $node['version'] = $version;
      $node['lat'] = (double) $node_xml->attributes()->lat;
      $node['lon'] = (double) $node_xml->attributes()->lon;
      $node['changeset'] = (integer) $node_xml->attributes()->changeset;
      $node['user'] = (string) $node_xml->attributes()->user;
      $node['uid'] = (integer) $node_xml->attributes()->uid;
      $node['time'] = (string) $node_xml->attributes()->timestamp;

      $tags = array();
      foreach ($node_xml->tag as $tag_xml) {
        $k = (string) $tag_xml->attributes()->k;
        $v = (string) $tag_xml->attributes()->v;
        $tags[$k] = $v;
        $tag_keys[$k] = true;
      }
      $node['tags'] = $tags;
      
      $nodes[$version] = $node;
    }
  }
}
?>
<html>
<? echo printCopyright() ?>
<head>
  <title>Deep Diff of Node #<? echo $id ?></title>
  <link rel='stylesheet' type='text/css' media='screen,print' href='style.css'/>
  <script src="http://www.google.com/jsapi"></script>
  <script>
    google.load("jquery", "1");
  </script>
  <script>
  $(function() {
    $(".collapse").click(function() {
        var o = this;
        var p = $(this);
        while (!$(p).is("table")) {
            p = $(p).parent();
        }
        $(".collapse",p).each(function(i) {
            if (this == o) {
                $("tr",p).find("td:eq(" + (i+1) + ")").css("display","none");
            }
        });
        $(this).parent().css("display","none");
        $(p).siblings(".reset_collapse").html("<p><a href='#' class='show_all_collapsed'>Show All</a></p>").find(".show_all_collapsed").click(function(){
            $("th,td",p).css("display","");
            $(this).parent().remove();
            return false;
        });
        return false;
    });
  });
  </script>
</head>
<body>
  <h3>Node ID <? echo $id ?></h3>
  <hr />

  <div>
  <table>
    <tr>
      <td>&nbsp;</td>
      <?
// sort ways by version number
// if we need to grab from both fosm and osm, this will ensure the correct order
// is displayed
ksort($nodes);
foreach($nodes as $n) {
  if ($n['changeset'] >= 1000000000)
    print "<td class='fosm'>";
  else
    print "<td>";

  print "Ver {$n['version']} [<a href='#' class='collapse'>x</a>]";

  if ($n['changeset'] >= 1000000000)
    print " <span class='fosm'>fosm</span>";

  print "</td>";
}
      ?>
    </tr>
    
    <tr>
      <td style='background:#aaa;' colspan='<? echo count($nodes) + 1 ?>'>Primitive Info</td>
    </tr>

    <? echo timeLine($nodes) ?>
    <? echo wayLine($nodes, 'changeset', true, "Changeset#", "http://osm.org/browse/changeset/") ?>
    <? echo wayLine($nodes, 'user', true, "User", "http://osm.org/user/") ?>
    <? echo wayLine($nodes, 'lat', true, "Lat") ?>
    <? echo wayLine($nodes, 'lon', true, "Lon") ?>
    <tr>
      <td style='background:#aaa;' colspan='<? echo count($nodes) + 1 ?>'>Tags</td>
    </tr>
    <?
foreach (array_keys($tag_keys) as $key) {
  print tagLine($nodes, $key, $key);
}
    ?>
  </table>
  <div class="reset_collapse"><!-- --></div>
  </div>
</body>
</html>
