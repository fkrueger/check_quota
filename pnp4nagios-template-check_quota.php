<?php

/*
 *   PNP4NAGIOS template for check_quota from 2014-04-23
 *   (c) 2011,2014 by Frederic Krueger / fkrueger-dev-checkquota@holics.at
 *    
 *   Licensed under the Apache License, Version 2.0
 *   There is no warranty of any kind, explicit or implied, for anything this software does or does not do.
 *
 *   Updates for this piece of software could be available under the following URL:
 *     GIT: https://github.com/fkrueger/check_quota
 *     Home: http://dev.techno.holics.at/check_quota/
 *
 *   Requires: pnp4nagios
 *
 */


$graphtype = "nice";  # nice or boring
$coloring = "nice";   # nice or boring, see definitions right below

$unittouse = "GB";
$divtouse = "1073741824";

$DEBUG = 0;


## init
## the coloring
$nicercolors = array (
 'dark' => array(
  'cc3118', 'cc7016', 'c9b215',  # red orange yellow
  '24bc14', '1598c3', 'b415c7',  # green blue pink
  '4d18e4'                       # purple
 ),
 'light' => array(
  'ea644a', 'ec9d48', 'ecd748',  # red orange yellow
  '54ec48', '48c4ec', 'de48ec',  # green blue pink
  '7648ec'                       # purple
 )
);

$boringcolors = array(
  'dark' => array(
    '000000', '222222', '444444', '666666', '888888', 'aaaaaa', 'cccccc', 'eeeeee'
  ),
  'light' => array(
    '111111', '333333', '555555', '777777', '999999', 'bbbbbb', 'dddddd', 'ffffff'
  )
);





## main
$usedcolors = ($coloring == "nice" ? $nicercolors : $boringcolors);

$images = array();
if ($graphtype == "nice")
{
  $images = nice_graph ($this->DS, "user", $usedcolors, $unittouse, $divtouse);
}

else

{
  $images = dflt_graph ($this->DS, "user", $usedcolors, $unittouse, $divtouse);
}

$numcolors = sizeof($usedcolors['dark']);

# now create the picture defs
for ($i=0; $i < sizeof($images); $i++)
{
  $curstart = $images[$i]['start'];
  $curend = $images[$i]['end'];
  $curdef = $images[$i]['def'];

  $txtrank = "$curstart to $curend";
  $opt[$i] =  " --title \"Used disk space (Rank $txtrank) for " . $this->MACRO['DISP_HOSTNAME'] . ' / ' . $this->MACRO['DISP_SERVICEDESC'] . "\" ";
  $opt[$i] .= " --font DEFAULT:7: --slope-mode " .($graphtype == "nice" ? " --right-axis $divtouse:0 " : "");
  $def[$i] = $curdef;
  $ds_name[$i] = "Used disk space - Rank $txtrank";
}

## fin.



function nice_graph ($cur_ds, $dsprefix = "temp", $usedcolors = array(), $unittouse = "GB", $divtouse = 1073741824)
{
  global $DEBUG;

  $numcolors = sizeof($usedcolors['dark']);
  $dsprefixlc = strtolower($dsprefix);

  $ds_data = array();      # assoc
  $ds_keynames = array();  # "DP<num>" => array(key,val)
  $ds_names = array();     # assoc val['NAME'] => "DP<num>"

  $tmpdef = "";   # which we return once we re done here

  # 1. collect names and values
  $linecnt = 1;
  reset ($cur_ds);
  foreach ($cur_ds as $key => $val)
  {
    $val['NAME'] = preg_replace ("/#/", "_", $val['NAME']);
    if (! isset($ds_data[ $val['NAME'] ]))  # key = number (0..99), without leading zeroes
    {
      if ((strpos(strtolower($val['NAME']), $dsprefixlc) !== false) and (strpos(strtolower($val['NAME']), $dsprefixlc) == 0))
      {
        $ds_data[ sprintf("%015d###%s", $val['ACT'], $val['NAME']) ] = array( 'key' => $key, 'val' => $val );
      }
    } # end if new key gotten
    else
    {
      $tmpdef .= "DupKey$key: " .$val['NAME']. "  ";
    } # end if dupe key gotten

    $linecnt++;
  } # end foreach name/value collector

  $lastds=$linecnt-1; # ds_data goes from 1..$lastds

  # the following is the number of images we are going to create.
  $numofimages = floor($lastds / $numcolors);
  if (($numofimages == 0) and ($lastds > 0)) { $numofimages = 1; }

  # 2. sort data in reverse alphabetical order
  reset ($ds_data); krsort ($ds_data);

  # 3. get keynames at current position for later referencing
  $linecnt = 1;
  foreach ($ds_data as $k => $v)   # go from back to front because of the nature of traceroute and pings
  {
    $ds_keynames["DP$linecnt"] = $k;
    $ds_names[$v['val']['NAME']] = "DP$linecnt";
    $linecnt++;
  } # end foreach key in order

  $images = array();

  # 4. create images
  for ($num = $numofimages; $num > 0; $num--)
  {
    $curnum = $numofimages - $num;
    $curdef = "";
    $curstart = $curnum * $numcolors +1;
    $curend = (($curnum+1) * $numcolors);
    if ($curend > $lastds) { $curend = $lastds; }

    for ($xx = $curstart; $xx <= $curend; $xx++)
    {
      $v = $ds_data [$ds_keynames["DP${xx}"]];
      $curdef .= "DEF:DP${xx}=" .$v['val']['RRDFILE']. ":" .$v['val']['DS']. ":AVERAGE ";
    } # end for all dp of image - def


    # use cdef to have other than bytes values (ie. 1073741824 => GB, 1024768 => MB)
    for ($xx = $curstart; $xx <= $curend; $xx++)
    {
      $v = $ds_data [$ds_keynames["DP${xx}"]];
      $curdef .= sprintf ("CDEF:%s%s=%s", $v['val']['NAME'], ($DEBUG?"--".$ds_keynames["DP$xx"]:""), "DP$xx,$divtouse,/"). "  ";
    } # end for all dp of image - cdef

    for ($xx = $curstart; $xx <= $curend; $xx++)
    {
      $s = "";    # in case we wanna :STACK later
      $curprek = "DP${xx}";
      $curk = "";

      # get a fine label
      $prtname = "";
      if (isset($ds_keynames[$curprek]))
      {
        $curk = $ds_keynames[$curprek];
        $prtname = $ds_data[$curk]['val']['NAME'];
      }
      else
      { $prtname = "${dsprefix}_$xx"; }

      $prtname = preg_replace ("/_/", " ", $prtname);
      $prtname = sprintf ("%-15s", substr($prtname, 0, 15));

      $curdef .= "AREA:" .$ds_data[$curk]['val']['NAME']. "#" .$usedcolors['light'][($xx-1)%$numcolors]. ":\"$prtname\" ";
      $curdef .= sprintf ("GPRINT:%s:LAST:\"  Cur %s %s\" ", $ds_data [$curk]['val']['NAME'], "%9.3lf", $unittouse);
      $curdef .= sprintf ("GPRINT:%s:MIN:\" Min Avg Max  %s %s\" ", $ds_data [$curk]['val']['NAME'], "%9.3lf", $unittouse);
      $curdef .= sprintf ("GPRINT:%s:AVERAGE:\"%s %s\" ", $ds_data [$curk]['val']['NAME'], "%9.3lf", $unittouse);
      $curdef .= sprintf ("GPRINT:%s:MAX:\"%s %s\\c\" ", $ds_data [$curk]['val']['NAME'], "%9.3lf", $unittouse);
    } # end for all dp of image - area

    for ($xx = $curstart; $xx <= $curend; $xx++)
    {
      $curdef .= sprintf ("LINE1:%s#%s", $ds_data [ $ds_keynames["DP$xx"] ]['val']['NAME'], $usedcolors['dark'][floor($xx-1)%$numcolors]). "  ";
    } # end for all dp of image - line1

    $curdef .= "COMMENT:\"Command " .$val['TEMPLATE']. " (template\: nice_graph)\\r\" ";
    $images[$curnum] = array('start' => $curstart, 'end' => $curend, 'def' => $curdef);
  } # end for all created images

  return $images;
} # end func nice_graph



function dflt_graph ($cur_ds, $dsprefix = "temp", $usedcolors = array(), $unittouse = "GB", $divtouse = 1073741824)
{
  global $DEBUG;
  $tmpdef = "";

  $numcolors = sizeof($usedcolors['dark']);

  $linecnt=1;
  foreach ($cur_ds as $key => $val)
  {
    if ((strpos($val['NAME'], $dsprefix) !== false) and (strpos($val['NAME'], $dsprefix) >= 0))
    {
      $curcolor = (isset($usedcolors['dark'][$linecnt%7])) ? $usedcolors['dark'][$linecnt%7] : $usedcolors['light'][$linecnt%7];
      $tmpdef .= "DEF:DP${key}=" .$val['RRDFILE']. ":" .$val['DS']. ":AVERAGE ";
      $tmpdef .= "LINE1:DP${key}#${curcolor}:\"" .sprintf("%-25s", $val['NAME']). "\" ";
      $tmpdef .= "GPRINT:DP${key}:LAST:\"Current %6.4lf %S" .$val['UNIT']. "\" ";
      $tmpdef .= "GPRINT:DP${key}:AVERAGE:\"Average %6.4lf %S" .$val['UNIT']. "\\l\" ";
      $linecnt++;
    }
  }

  $tmpdef .= "COMMENT:\"\\r\" ";
  $tmpdef .= "COMMENT:\"Command " . $val['TEMPLATE'] . " (template\: dflt_graph)\\r\" ";

  return array('start' => 1, 'end' => $linecnt, 'def' => $tmpdef);
} # end func dflt_graph


?>
