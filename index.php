<!DOCTYPE html>
<?php
/**
 * NTUST-ics-Class-Schedule: Make .ics format class Schedule
 *
 * @package NTUST-ics-Class-Schedule
 * @author Neson <neson@dex.tw>
*/

/** Config */
$semester = 1022; //學期
$sd = 20140217; //開始上課日期

/**
 * Get info of a class
 *
 * @param int $course
 * @param int $semester
 * @return array $data
 */
function getClassData($course, $semester) {
  if (preg_match('/^[A-Z0-9]{9}$/', $course) && preg_match('/^[0-9]+[12]$/', $semester)) {
    $http="http://info.ntust.edu.tw/faith/edua/app/qry_linkoutline.aspx?semester=".$semester."&courseno=".$course;
    $content = file_get_contents($http);
    // 拆
    $contenta = explode('<span id="lbl_courseno"><font color="SlateGray">', $content);
    $contentb = explode('</font></span>', $contenta[1]);
    if ($contentb[0] == $course) {
      $data['code']=$contentb[0];

      $contenta = explode('<span id="lbl_coursename"><font color="SlateGray">', $content);
      $contentb = explode('</font></span>', $contenta[1]);
      $data['title']=$contentb[0];

      $contenta = explode('<span id="lbl_timenode"><font color="SlateGray">', $content);
      $contentb = explode('</font></span>', $contenta[1]);
      $i2 = 0;
      $te = explode(' ', $contentb[0]);
      foreach($te as $index => $value){
          if($value != ""){
              $valueea = explode('(', $value);
              $valueeb = explode(')', $valueea[1]);
              $data['time'][$i2] = $valueea[0];
              $data['location'][$i2] = $valueeb[0];
              $i2++;
          }
      }

      $contenta = explode('<span id="lbl_teacher"><font color="SlateGray" size="3">', $content);
      $contentb = explode('</font></span>', $contenta[1]);
      $data['lecturer']=$contentb[0];
    } else {
      return false;
    }
  } else {
    return false;
  }
  return $data;
}

$wd = array(
  "1" => "MO",
  "2" => "TU",
  "3" => "WE",
  "4" => "TH",
  "5" => "FR",
  "6" => "SA",
  "7" => "SU"
);

// 定義每節課時間、星期對應
$t[1][0] = '0830';
$t[2][0] = '0930';
$t[3][0] = '1030';
$t[4][0] = '1130';
$t[5][0] = '1230';
$t[6][0] = '1330';
$t[7][0] = '1430';
$t[8][0] = '1530';
$t[9][0] = '1630';
$t[10][0] = '1730';
$t['A'][0] = '1825';
$t['B'][0] = '1920';
$t['C'][0] = '2015';
$t['D'][0] = '2110';

$t[1][1] = '0920';
$t[2][1] = '1020';
$t[3][1] = '1120';
$t[4][1] = '1220';
$t[5][1] = '1320';
$t[6][1] = '1420';
$t[7][1] = '1520';
$t[8][1] = '1620';
$t[9][1] = '1720';
$t[10][1] = '1820';
$t['A'][1] = '1915';
$t['B'][1] = '2010';
$t['C'][1] = '2105';
$t['D'][1] = '2200';

$d = array(
  "M" => "1",
  "F" => "5",
  "T" => "2",
  "S" => "6",
  "W" => "3",
  "U" => "7",
  "R" => "4"
);

/** exe */
if ($_GET['press']) {
  /** get data */
  $sdu = strtotime($sd);
  $swd = date('w', $sdu);  // 轉換開始上課日期
  $course = $_GET['content'];
  $course = ereg_replace(",", " ", $course);
  $course = ereg_replace(":", " ", $course);
  $course = ereg_replace("\(", " ", $course);
  $course = ereg_replace("\)", " ", $course);
  $course = ereg_replace("  ", " ", $course);
  if ($course == "" || $course == null) {
  	$error_no_data = 1;
  	$_GET['press'] = "";
  } else {
    $courseData = file_get_contents('data/courseData' . $semester . '.json');
    $courseData = json_decode($courseData, true);
    $course = explode(' ', $course);
    foreach ($course as $courseCode) {
      if (!preg_match('/^[A-Z0-9]{9}$/', $courseCode)) continue;
      if (!$courseData[$courseCode]) {
        $courseData[$courseCode] = getClassData($courseCode, $semester);
      }
      $data[$courseCode] = $courseData[$courseCode];
    }

    if (empty($data)) {
      $has_error = true;
      $error_type = 'no_data';
    }

    /** arrange and merge events */
    $merged_data = $data;
    foreach ($merged_data as $cid => $course_inf) {
      foreach ($course_inf['time'] as $cn => $dat) {
        $weekday = substr($dat, 0, 1);
        $time = substr($dat, 1, 2);
        $merged_data[$cid]['class'][$cn]['weekday'] = $weekday;
        $merged_data[$cid]['class'][$cn]['start'] = $t[$time][0];
        $merged_data[$cid]['class'][$cn]['end'] = $t[$time][1];
        $merged_data[$cid]['class'][$cn]['location'] = $merged_data[$cid]['location'][$cn];
      }
    }

    foreach ($merged_data as $cid => $course_inf) {
      foreach ($course_inf['class'] as $cn => $dat) {
        if ($cn > 0 &&
          $merged_data[$cid]['class'][$cn]['weekday']
          == $merged_data[$cid]['class'][$cn-1]['weekday'] &&
          $merged_data[$cid]['class'][$cn]['location']
          == $merged_data[$cid]['class'][$cn-1]['location'] &&
          $merged_data[$cid]['class'][$cn-1]['end']
          - $merged_data[$cid]['class'][$cn]['start'] < 15) {

          $merged_data[$cid]['class'][$cn]['start'] = $merged_data[$cid]['class'][$cn-1]['start'];
          unset($merged_data[$cid]['class'][$cn-1]);
        }
      }
    }

    /** print data */
    $fileKey = md5(serialize($data));
    $file = "ics/".$semester."-".$fileKey.".ics";
    if (!file_exists($file)) {

      $handle= fopen($file,'w');
      $txt = "BEGIN:VCALENDAR\nPRODID:ntust.pokaichang.com\nVERSION:2.0\nCALSCALE:GREGORIAN\nMETHOD:PUBLISH\nX-WR-CALNAME:課程表\nX-WR-TIMEZONE:Asia/Taipei\nX-WR-CALDESC:\nBEGIN:VTIMEZONE\nTZID:Asia/Taipei\nX-LIC-LOCATION:Asia/Taipei\nBEGIN:STANDARD\nTZOFFSETFROM:+0800\nTZOFFSETTO:+0800\nTZNAME:CST\nDTSTART:19700101T000000\nEND:STANDARD\nEND:VTIMEZONE";
      fwrite($handle,$txt);
      foreach ($merged_data as $course_inf) {
        foreach ($course_inf['class'] as $cn => $dat) {
          $weekday = $dat['weekday'];
          $daysafter = $d[$weekday]-$swd;
          if ($daysafter < 0) $daysafter+=7;

          $eyears = date("Y", $sdu);
          $emonths = date("m", $sdu);
          $edays = date("d", $sdu);
          $edate = date("Ymd", mktime(0,0,0,$emonths,$edays+$daysafter,$eyears));
          $dd = $d[$weekday];

          $txt = "\n\nBEGIN:VEVENT\n";
          fwrite($handle,$txt);

          $txt = "DTSTART;TZID=Asia/Taipei:".$edate."T".$dat['start']."00\n";
          fwrite($handle,$txt);

          $txt = "DTEND;TZID=Asia/Taipei:".$edate."T".$dat['end']."00\n";
          fwrite($handle,$txt);

          $txt = "RRULE:FREQ=WEEKLY;COUNT=18;BYDAY=".$wd[$dd]."\n";
          fwrite($handle,$txt);

          $txt = "SUMMARY:".$course_inf[title]."\nLOCATION:".$course_inf[location][$cn]."\nDESCRIPTION:授課教師: ".$course_inf[lecturer]."\n";
          fwrite($handle,$txt);

          $txt = "END:VEVENT\n";
          fwrite($handle,$txt);
        }
      }

      $txt = "END:VCALENDAR";
      fwrite($handle,$txt);
      fclose($handle);
    }

    /** Save Data */
    $courseData = json_encode($courseData);
    file_put_contents('data/courseData' . $semester . '.json' ,$courseData);
  }
}
?>
<html lang="zh-tw">
  <head>
    <meta charset="utf-8">
    <title>NTUST 課表行事曆製作工具</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="把台科大課表快速排進行事曆的工具，支援 iOS、Android、Mac 以及絕大多數行事曆軟體。會自動把上課地點和授課教師附註上去。">
    <meta name="author" content="Neson">
    <meta property="og:image" content="http://lh3.googleusercontent.com/-gRGcR3IhZ3I/USHkr92eAEI/AAAAAAAATVg/3Xp2W8XTFtY/s0/favicon.png">

    <link href="css/bootstrap.css" rel="stylesheet">
    <link href="css/animate.css" rel="stylesheet">
    <style type="text/css">
      body {
        padding-top: 40px;
        padding-bottom: 40px;
        background-color: #f5f5f5;
      }

      label {
      	font-size: 16px;
      	margin-top: 20px;
      }

      input {
      	margin-top: 0 !important;
      	margin-bottom: 0 !important;
      }

      .form-signin {
        max-width: 380px;
        padding: 19px 29px 29px;
        margin: 0 auto 20px;
        background-color: #fff;
        border: 1px solid #e5e5e5;
        -webkit-border-radius: 5px;
           -moz-border-radius: 5px;
                border-radius: 5px;
        -webkit-box-shadow: 0 1px 2px rgba(0,0,0,.05);
           -moz-box-shadow: 0 1px 2px rgba(0,0,0,.05);
                box-shadow: 0 1px 2px rgba(0,0,0,.05);
      }
      .form-signin-heading {
        font-size: 31.5px;
      }
      .form-signin .form-signin-heading,
      .form-signin .checkbox {
        margin-bottom: 10px;
      }
      .form-signin input[type="text"],
      .form-signin input[type="password"] {
        font-size: 16px;
        height: auto;
        margin-bottom: 15px;
        padding: 7px 9px;
      }
      .control-label {
        margin-left : 18px;
        text-indent : -18px ;
      }
      .btn {
        margin-bottom: 12px;
      }
      .modal-header {
      }
      .modal-body .nav-tabs {
        margin-bottom: 0px;
      }
      .modal-footer .btn {
        margin-bottom: 0px;
      }
      .accordion-heading {
        background-color: #f5f5f5;
      }
      .accordion-toggle {
        color: black;
      }
      .data-group, .data-heading, .data-collapse, .data-heading > *, .data-collapse > * {
        background-color: white;
        padding: 0 !important;
        border: 0 !important;
      }
      .load {
        -webkit-transition: 1s;
           -moz-transition: 1s;
             -o-transition: 1s;
                transition: 1s;
      }
      .then_what, .then_what * {
        text-align: right;
        color: #888;
      }
      .then_what a {
        text-decoration: underline;
      }
      @media (max-width: 979px) {
        body {
          padding-top: 24px !important;
        }
      }
    </style>
    <link rel="icon" href="favicon.png" type="image/x-icon">
    <link href="favicon.png" rel="image_src" type="image/jpeg">
    <link href="css/bootstrap-responsive.css" rel="stylesheet">
    <script type="text/javascript" src="js/jquery.min.js"></script>
    <script type="text/javascript" src="js/bootstrap.min.js"></script>
    <script type="text/javascript">

      var _gaq = _gaq || [];
      _gaq.push(['_setAccount', 'UA-35493848-2']);
      _gaq.push(['_trackPageview']);

      (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
      })();

    </script>
    <script type="text/javascript">
      var _gaq = _gaq || [];
      _gaq.push(['_setAccount', 'UA-35493848-4']);
      _gaq.push(['_trackPageview']);

      (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'stats.g.doubleclick.net/dc.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
      })();
    </script>
  </head>
  <body>
    <div id="fb-root"></div>
      <script>(function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = "//connect.facebook.net/zh_TW/all.js#xfbml=1&appId=132913846761101";
        fjs.parentNode.insertBefore(js, fjs);
      }(document, 'script', 'facebook-jssdk'));</script>
    <div class="container">
      <form action="#get" class="form-signin" method="get" onsubmit="return validate_form(this);">
      <h1 class="form-signin-heading">NTUST <br>課表行事曆製作工具 <small><?php echo $semester; ?></small></h1>
        <div class="accordion-group">
          <div class="accordion-heading">
            <a class="accordion-toggle collapsed" data-toggle="collapse" data-parent="#accordion2" href="#collapseOne">
              <i class="icon-question-sign"></i> 這啥？
            </a>
          </div>
          <div id="collapseOne" class="accordion-body collapse" style="height: 0px;">
            <div class="accordion-inner">
              <p>把台科大課表快速排進行事曆的工具。會自動把上課地點和授課教師附註上去。
              <a href="#how0" data-toggle="modal">詳細</a>。</p>
              <p>有鑒於把課表一個一個手動 key-in 到行事曆會死掉所以做了這個東西。支援 iOS、Android、Mac 以及絕大多數可匯入 .ics 格式的行事曆軟體。</p>
              <p>
                <iframe src="http://ghbtns.com/github-btn.html?user=Neson&repo=NTUST-ics-Class-Schedule&type=watch" allowtransparency="true" frameborder="0" scrolling="0" width="62" height="22"></iframe>
                <iframe src="http://ghbtns.com/github-btn.html?user=Neson&repo=NTUST-ics-Class-Schedule&type=fork" allowtransparency="true" frameborder="0" scrolling="0" width="55" height="22"></iframe>
              </p>

            </div>
          </div>
        </div>

        <hr>

        <div class="control-group content">
          <label class="control-label" for="content">1. 輸入您的所有課程代碼，以空白分隔。<br>進 <a href="https://stu255.ntust.edu.tw/ntust_stu/stu.aspx" target="_blank">學生資訊系統</a> 或 <a href="https://www.ntust.cc/simulate/" target="_blank">NTUST.CC</a> 把選課資料全部複製貼上來也可以。<a href="#how1" data-toggle="modal">圖。</a></label>
          <input name="content" id="content" type="text" class="input-block-level" placeholder="" style="height: 100px;" value="<?php echo $_GET['content']; ?>">
        </div>

        <label for="press">2.</label>
        <input type="submit" name="press" value="按下去" id="press" class="btn btn-large btn-block" <?php if($_GET['press']) echo "disabled=\"disabled\""; ?> >

        <div class="load" style="<?php if(!$_GET['press']) echo "height: 0;"; ?> overflow: hidden;">
          <label>3. 等</label>
          <div class="progress progress-info <?php if(!$_GET['press']) echo "progress-striped active"; ?>">
            <div class="bar" style="width: 100%;"></div>
          </div>
        </div>

        <?php
        if ($has_error) {
          switch ($error_type) {
            case 'no_data':
            echo '<div class="alert alert-block alert-error"><button type="button" class="close" data-dismiss="alert">×</button><h4 class="alert-heading">沒有課啊！</h4><p>找不到課程代碼片段。請確認您貼上的文字包含課程代碼，且以空白分隔。<br><a href="?">重來</a></p></div>';
              break;
            default:
              echo '<div class="alert alert-block alert-error"><button type="button" class="close" data-dismiss="alert">×</button><h4 class="alert-heading">有誤。</h4><br><a href="?">重來</a></div>';
              break;
          }
        }
        if (!$_GET['press'] || $has_error) echo "<!--";
        ?>

        <label id="get" for="get">4.</label>
        <a id="get" class="btn btn-large btn-block btn-primary" href="<?php echo "ics/".$semester."-".$fileKey.".ics"; ?>">取得日曆</a>
        <div class="then_what">(<a href="#how0" data-toggle="modal">然後呢?</a>)</div>
        <center>
          <br>
          <a class="collapsed" data-toggle="collapse" data-parent="#accordion2" href="#collapsedata">顯示數據</a>
          |
          <a href="?">再來一次</a>
        </center>

        <div class="accordion-group data-group">
          <div id="collapsedata" class="accordion-body collapse data-collapse" style="height: 0px;">
            <div class="accordion-inner">
              <br>
              <pre>
<?php echo ereg_replace("  ", " ", print_r($merged_data, true)); ?>
              </pre>
            </div>
          </div>
        </div>

        <?php if(!$_GET['press'] || $has_error) echo "-->"; ?>
        <hr>
        <div class="fb-like" data-href="http://calendar.ntust.co/" data-send="true" data-show-faces="true" style="max-width: 100%; "></div>
      </form>
      <script type="text/javascript">
        function loadbar(){
        }
        $(".id").keydown(function(){
          $(".id").removeClass("error");
        });
        $(".content").keydown(function(){
          $(".content").removeClass("error");
        });
      </script>
    </div>

<!-- Modal -->
    <div id="how0" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3>用法</h3>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs">
          <li class="active"><a href="#how0_ios" data-toggle="tab">iOS</a></li>
          <li><a href="#how0_android" data-toggle="tab">Android</a></li>
          <li><a href="#how0_mac" data-toggle="tab">Mac</a></li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade active in" id="how0_ios">
            <h3>iOS</h3>
            <hr style="margin-top: 0; margin-bottom: 10px;">
            <p>直接在 iPhone/iPad 上開啟此網頁。</p>
            <img src="img/how0-ios.jpg"><br><br>
            <p>建議加入到新行事曆，以免混亂您原有的行事曆。</p>
            <img src="img/how0-ios-2.jpg"><br><br>
            <img src="img/how0-ios-3.jpg">
          </div>
          <div class="tab-pane fade" id="how0_android">
            <h3>Android</h3>
            <hr style="margin-top: 0; margin-bottom: 10px;">
            <p>前往 <a href="https://www.google.com/calendar/" target="_blank">https://www.google.com/calendar/</a>，匯入日曆檔案到 Google Calender，再與 Android 同步。</p>
            <img src="img/how0-android.jpg">
          </div>
          <div class="tab-pane fade" id="how0_mac">
            <h3>Mac</h3>
            <hr style="margin-top: 0; margin-bottom: 10px;">
            <p>直接按兩下，用行事曆打開它。建議匯入到新行事曆，以免混亂您原有的行事曆。</p>
            <img src="img/how0-mac.jpg">
            <img src="img/how0-mac-2.jpg">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="#" class="btn" data-dismiss="modal">　好　</a>
      </div>
    </div>
    <div id="how1" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="myModalLabel">　</h3>
      </div>
      <div class="modal-body">
        <img src="img/how1.jpg">
        <p><center>-或-</center></p>
        <img src="img/how1-2.jpg">
      </div>
      <div class="modal-footer">
        <a href="#" class="btn" data-dismiss="modal">　好　</a>
      </div>
    </div>
    <script type="text/javascript">
      function validate_required(field, c) {
        with (field) {
          if (value == null || value == "") {
            $(c).addClass("error");
            $(c).addClass("animated");
            $(c).addClass("shake");
            setTimeout('$(".control-group").removeClass("shake")', 1000);
            return false;
          }else{
            return true;
          }
        }
      }

      function validate_form(thisform) {
        with (thisform) {
          if (validate_required(content, ".content") == false) {
            content.focus();
            return false
          }
          $(".load").css("height","auto");
        }
        return true;
      }
    </script>
  </body>
</html>
