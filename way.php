<?php

include_once('osm_out.php');

if(!is_numeric($_GET['id'])) {
  exit;
}

$id = $_GET['id'];

$url = "http://api.fosm.org/api/0.6/way/$id/history";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "curl/deep_history_viewer");
$output = curl_exec($ch);

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// try OSM if gone from api.fosm.org
if($http_code == 410) {
  $url = "http://www.openstreetmap.org/api/0.6/way/$id/history";

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

$ways = array();
$tag_keys = array();
$way_refs = array();

// keep track of min/max versions in this /history call as fosm may not contain
// the full history
$min_version = 0;
$max_version = 0;
if ($xml->way > 0) {
  $min_version = $xml->way[0]->attributes()->version;
  $max_version = $min_version;
}

foreach ($xml->way as $way_xml) {
  $version = (integer) $way_xml->attributes()->version;
  if ($version < $min_version)
    $min_version = $version;
  if ($version > $max_version)
    $max_version = $version;
  $way['version'] = $version;
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

  $refs = array();
  foreach ($way_xml->nd as $nd_xml) {
    $ref = (string) $nd_xml->attributes()->ref;
    $refs[$ref] = true;
    $way_refs[$ref] = true;
  }
  $way['refs'] = $refs;
  
  $ways[$version] = $way;
}

// fosm didn't return the full history, so now lets get everything from version
// 1 to $min_version - 1 from osm
if ($min_version != 1) {
  $min_version = $max_version;
  $url = "http://www.openstreetmap.org/api/0.6/way/$id/history";

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

  foreach ($xml->way as $way_xml) {
    $version = (integer) $way_xml->attributes()->version;

    // just grab details from versions that are not in the fosm api result, and
    // are lower version numbers than the lowest fosm version number we found
    if ($version < $min_version) {
      $way['version'] = $version;
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

      $refs = array();
      foreach ($way_xml->nd as $nd_xml) {
        $ref = (string) $nd_xml->attributes()->ref;
        $refs[$ref] = true;
        $way_refs[$ref] = true;
      }
      $way['refs'] = $refs;

      $ways[$version] = $way;
    }
  }
}

?>

<head>
  <title>Deep Diff of Way #<? echo $id ?></title>
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
  <h3>Way ID <? echo $id ?></h3>
  <hr />

  <div>
  <table>
    <tr>
      <td>&nbsp;</td>
      <?
// sort ways by version number
// if we need to grab from both fosm and osm, this will ensure the correct order
// is displayed
ksort($ways);
foreach($ways as $n) {
  print "<td>Ver {$n['version']} [<a href='#' class='collapse'>x</a>]</td>";
}
      ?>
    </tr>

    <tr>
      <td style='background:#aaa;' colspan='<? echo count($ways) + 1 ?>'>Primitive Info</td>
    </tr>

    <? echo timeLine($ways) ?>
    <? echo wayLine($ways, 'changeset', true, "Changeset#", "http://osm.org/browse/changeset/") ?>
    <? echo wayLine($ways, 'user', true, "User", "http://osm.org/user/") ?>
    <tr>
      <td style='background:#aaa;' colspan='<? echo count($ways) + 1 ?>'>Tags</td>
    </tr>
    <?
foreach (array_keys($tag_keys) as $key) {
  print tagLine($ways, $key, $key);
}
    ?>
    <tr>
      <td style='background:#aaa;' colspan='<? echo count($ways) + 1 ?>'>Nodes</td>
    </tr>
    <?
foreach (array_keys($way_refs) as $ref) {
  print refLine($ways, $ref);
}
    ?>
  </table>
  <div class="reset_collapse"><!-- --></div>
  </div>
</body>
