<!DOCTYPE html>
<!-- *************************************************************************************************************************************** -->

<?php
require CONN;
require(PUBLIC_PATH . DS . "API/vendor/autoload.php");

use \Firebase\JWT\JWT;
//echo $_COOKIE['mode'];
$wipstime = microtime(true);

if (!isset($_COOKIE['mode']) || !isset($_COOKIE['shopid']) || !isset($_COOKIE['shopname'])) {
    header("Location: https://" . $_SERVER['SERVER_NAME'] . "/logoff.php");
}

$randnum = rand(111111, 999999);

$shopid = $_COOKIE['shopid'];
$usr = isset($_COOKIE['usr']) ? $_COOKIE['usr'] : ''; //checking usr
$empid = isset($_COOKIE['empid']) ? $_COOKIE['empid'] : '';
setcookie("interface", "2", time() + (86400 * 30), "/");
$date = date('m/d/Y');
if (isset($_COOKIE['full'])) {
    $mode = $_COOKIE['full'];
}

$lb = "no";
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} elseif (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    $lb = "yes";
} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}

$iPhone  = stripos($_SERVER['HTTP_USER_AGENT'], "iPhone");

$rocount = 0;
$stmt = "select count(*) c from repairorders where shopid = '$shopid'";
if ($query = $conn->prepare($stmt)) {
    $query->execute();
    $query->bind_result($rocount);
    $query->fetch();
    $query->close();
}

// set the interface on the employee record

if (!empty($empid) && $empid != "Admin" && $empid != "demo") {
    $stmt = "update employees set interface = '2' where id = " . $empid;
    //echo $stmt;
    if ($query = $conn->prepare($stmt)) {
        $query->execute();
        $conn->commit();
        $query->close();
    } else {
        echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    }
}

$stmt = "select readonly,shopnotice,autoshowta,newpackagetype,alldatausername,masterinterface,companystate,package,lower(status),datestarted,merchantaccount,upper(showstatsonwip),companyemail,logo,contact,ts from company where shopid = ?";

if ($query = $conn->prepare($stmt)) {

    $query->bind_param("s", $shopid);
    $query->execute();
    $query->bind_result($readonly, $shopnotice, $autoshowta, $plan, $motortype, $masterinterface, $shopstate, $sbpackage, $sbstatus, $datestarted, $shopmerchant, $showstatsonwip, $companyemail, $logo,$contact,$lastUpdated);
    $query->fetch();
    $query->close();
} else {
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
}

$stmt = "select motor,sortwipbysa,360popup,failedpayment from settings where shopid = ?";
if ($query = $conn->prepare($stmt)){
    $query->bind_param("s",$shopid);
    $query->execute();
    $query->bind_result($motor,$sortwipbysa,$popup360,$failedpayment);
    $query->fetch();
    $query->close();
}

$workflownewtab = "no";
$matco = $_COOKIE['matco'] ?? 'no';

$showQuickVideoBanner = false;
$QSBannerDaysLimit = 5;
$days_started = ceil( (time() - strtotime($lastUpdated)) / 86400);
if($sbpackage == 'Trial' && $days_started <= $QSBannerDaysLimit){
    $showQuickVideoBanner = true;
}

$showmatcovideo = $quotesbeta = "no";

$video_url = "https://www.youtube.com/embed/_fpZWTqjhSc"; //shopboss quick start video URL

if ($matco == 'yes'){
    $video_url = "https://www.youtube.com/embed/84sSGgz5GUc";

    if ($sbpackage == 'Trial')
    {
        $stmt = "select roid from repairorders where shopid = ? and shopid = origshopid limit 1";
        if ($query = $conn->prepare($stmt)) {
            $query->bind_param("s", $shopid);
            $query->execute();
            $query->store_result();
            $num_roid_rows = $query->num_rows;
            if ($num_roid_rows < 1)
            $showmatcovideo = 'yes';
        }
    }
}

$stmt = "select id from quotesbeta where shopid = ? limit 1";
if ($query = $conn->prepare($stmt)) {
    $query->bind_param("s", $shopid);
    $query->execute();
    $query->store_result();
    $num_roid_rows = $query->num_rows;
    if ($num_roid_rows > 0)
    $quotesbeta = 'yes';
}

$stmt = "select opennewtab from kanbansettings where shopid = ?";
if ($query = $conn->prepare($stmt)) {
    $query->bind_param("s", $shopid);
    $query->execute();
    $query->bind_result($workflownewtab);
    $query->fetch();
    $query->close();
}


if (strtolower(substr($shopstate, 0, 2)) == "fl") {
    // now check for florida docs

    $path = COMPONENTS_PUBLIC_PATH . "/invoices/$shopid";
    if (!file_exists($path)) {
        mkdir($path);
        mkdir($path . "/temp");
        // copy the files
        copy(COMPONENTS_PUBLIC_PATH . "/invoices/florida/floridaestimate.asp", $path . "/floridaestimate.asp");
        copy(COMPONENTS_PUBLIC_PATH . "/invoices/florida/pdffloridaestimate.asp", $path . "/pdffloridaestimate.asp");
        copy(COMPONENTS_PUBLIC_PATH . "/invoices/florida/printpdfro.asp", $path . "/printpdfro.asp");
    }
}

if ($empid == "Admin") {
    $ChangeNotice = "YES";
    $accountingaccess = "YES";
    $reportsaccess = "YES";
    $settingsaccess = "YES";
    $empemail = 'admin@shopboss.net';
    $dashboardaccess = 'YES';
    $EditInventory = 'YES';
} else {
    $stmt = "select upper(changeshopnotice),upper(accounting),upper(ReportAccess),upper(CompanyAccess),EmployeeEmail,upper(DashboardAccess),upper(EditInventory),lower(jobdesc),concat(employeefirst,' ',employeelast) from employees where id = ? and shopid = ?";

    if ($query = $conn->prepare($stmt)) {

        $query->bind_param("is", $empid, $shopid);
        $query->execute();
        $query->bind_result($ChangeNotice, $accountingaccess, $reportsaccess, $settingsaccess, $empemail, $dashboardaccess, $EditInventory,$jobdesc,$empname);
        $query->fetch();
        $query->close();
    } else {
        echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    }
}

$motorfull = "no";
$motorest = "no";
$pid = "";

if ($plan == "gold") {

    $motorfull = "no";
    $motorest = "yes";
    $pid = "40";
} elseif ($plan == "platinum") {

    $motorfull = "yes";
    $motorest = "no";
    $pid = "39";
} elseif ($plan == "silver") {

    $motorfull = "no";
    $motorest = "no";
    $pid = "";
} elseif ($plan == "none") {

    if ($motortype == "motorfull") {

        $motorfull = "yes";
        $motorest = "no";
        $pid = "39";
    } elseif ($motortype == "motorest") {

        $motorfull = "no";
        $motorest = "yes";
        $pid = "40";
    } else {

        $motorfull = "no";
        $motorest = "no";
        $pid = "";
    }
}


if ($autoshowta == "yes") {
    $showta = "block";      // display:block for tech div
    $showtabtn = "none";    // display:none for button
} else {
    $showta = "none";       // display:none for tech div
    $showtabtn = "block";   // display:block for button
}
$userfirst = $userlast = '';
if (isset($_COOKIE['username'])) {
    $arr = explode(' ', $_COOKIE['username']);
    $userfirst = $arr[0];
    $userlast = (isset($arr[1]) ? $arr[1] : '') . ' (' . $_COOKIE['shopname'] . ')';
}


if ($sbpackage == 'Trial' && $sbstatus == 'active') {
    $filter_type = 'Trial';
    $diff = strtotime(date('Y-m-d')) - strtotime($datestarted);
    $trialdays = 30 - abs(round($diff / 86400));
} elseif (!empty($plan) && $plan != 'none')
    $filter_type = $plan;
else
    $filter_type = 'All';

if (in_array($shopid, array('6062', '8102', '13846', '7550', '11489', '14627', '12386', '3263', '17360', '13931', '14401', '16665', '17032', '11198', '15226', '3741', '15788', '3439', '17684', '8395', '16200', '12869', '14642', '10242', '18101', '17620', '17910', '17302')))
    $filter_type = 'prodemand;' . $filter_type;

if (in_array($shopmerchant, array('360', 'tnp', 'authorize.net', 'cardknox')))
    $filter_type .= ";" . $shopmerchant;
else
    $filter_type .= ";nopayments";

if ($shopmerchant != '360') $filter_type .= ";non360";

if ($sbpackage == 'Paid' && $sbstatus == 'active' && $plan != "platinum") {
    $stmt = "select id from companyadds where shopid = '$shopid' and `name` = 'Boss Board'";
    if ($query = $conn->prepare($stmt)) {
        $query->execute();
        $query->store_result();
        $num_roid_rows = $query->num_rows;
        if ($num_roid_rows < 1)
            $filter_type .= ";BossBoard Inactive";
    }
}

if (($plan == 'none' || empty($plan)) && $sbpackage != 'Trial')
    $filter_type .= ";Usage";

if (in_array($shopid, $dvibeta))
    $filter_type .= ";dvibeta";

if (in_array($shopid, $vipshops))
    $filter_type .= ";vip";

if ($shopid == 'demo')
    $filter_type .= ";demo";

if ($matco == 'yes')
    $filter_type .= ";matco";
else
    $filter_type .= ";nonmatco";

if (in_array($shopid, $betavin))
    $filter_type .= ";beta-vin";

$PrivateKey = '447f78ce-9068-4e0d-3a92-0551a6ebc077';

if ($empid != "Admin") {
    $userData = [
        'email' => (!empty($empemail) ? $empemail : $companyemail),
        'id' => $empid,
        'name' => $_COOKIE['usr'] . ' - ' . $shopid
    ];
} else {
    $exparr = explode(',', $_COOKIE['admindata']);
    $userData = [
        'email' => $exparr[3] ?? 'admin@shopboss.net',
        'id' => $exparr[0] ?? '0',
        'name' => (isset($exparr[1]) ? $exparr[1] . ' ' . $exparr[2] : 'Admin') . ' - ' . $shopid
    ];
}
$ssoToken = JWT::encode($userData, $PrivateKey, 'HS256');
$cannyurl = "https://canny.io/api/redirects/sso?companyID=61a9055f38287d52f4e1e4cf&ssoToken=" . $ssoToken . "&redirect=https://feedback.shopbosspro.com";

if ($failedpayment == 'yes') {

    if(!isset($_COOKIE['failedpayment']))
    {
         if ((stripos($sn, 'matcosms.com') !== false))
         $servername = '.matcosms.com';
         else
         $servername = '.shopbosspro.com';

         $expiration = time() + (24 * 3600);

         $_COOKIE['failedpayment'] = setcookie("failedpayment", "yes", $expiration, "/", $servername);

         $paybalancelink = '';

        $url = "https://api.armatic.com/customers?query=" . $shopid;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            "Accept: application/json",
            "Authorization: Bearer YcRuU2VlFBs25r99avyhJC1fsV25Uoia8cOJwWHshq9u_sJCCs9y0vJ1sGOjxaiJ8ljKfbgqCrqpW25Z6eCgfAPgfo8VeBE1WXg=",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($curl);
        curl_close($curl);

        $json = json_decode($res, true);

        $invoicecount = 0;

        if (isset($json['customers'])) {
            foreach ($json['customers'] as $shop) {
                if ($shop['account_number'] == $shopid) {
                    $paybalancelink = $shop['pay_balance_link'];
                    break;
                }
            }
        }
    }
    else
    $failedpayment = "no";
}

$capitalarr = array();

if($popup360=='yes' && $shopmerchant!='cardknox' && $failedpayment == "no" && !isset($_COOKIE['hidecapital']) && ($jobdesc=='owner' || strtoupper($contact)==strtoupper($empname) || $_COOKIE['empid']=='Admin'))
{
  if($shopmerchant!='360')
  $capitalarr = array('message' => "Get integrated payments with BOSS PAY", 'url' => 'https://360payments.com/partner-with-us/shop-boss/?tag=shopboss');
  else
  {
    $data = array("key" => "242dbb8a-6eab-46bf-ac9e-ec878d7aaa13", "merchant_id" => $shopid, "partner_id" => "a0Fi0000007vT9TEAU");
    $jsonEncodedData = json_encode($data);
    $curl = curl_init();
    $opts = array(
        CURLOPT_URL             => 'https://us-central1-capital-prod.cloudfunctions.net/x360capital/get-offers',
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_CUSTOMREQUEST   => 'POST',
        CURLOPT_POST            => 1,
        CURLOPT_POSTFIELDS      => $jsonEncodedData,
        CURLOPT_HTTPHEADER  => array('Content-Type: application/json', 'Content-Length: ' . strlen($jsonEncodedData))
    );
    curl_setopt_array($curl, $opts);
    $result = curl_exec($curl);
    $json = json_decode($result);
    if (isset($json->signup_url) && !empty($json->signup_url) && !empty($json->message))
    $capitalarr = array('message' => $json->message, 'url' => $json->signup_url);
  }
}
?>

<!-- Beamerbutton Scripts Start -->
<script async="false">
    var beamer_config = {
        product_id: "KdwxtFFu19213", //DO NOT CHANGE: This is your product code on Beamer
        selector: 'beamerButton',
        filter: '<?= $filter_type ?>',
        user_firstname: "<?= addslashes($userfirst) ?>",
        user_lastname: "<?= addslashes($userlast) ?>",
        user_id: '<?= $shopid ?>',
        user_email: '<?= $empemail ?>'
    };
</script>
<script type="text/javascript" src="https://app.getbeamer.com/js/beamer-embed.js" defer="defer"></script>

<!--[if IE 9]>         <html class="ie9 no-focus"> <![endif]-->
<!--[if gt IE 9]><!-->

<!-- *************************************************************************************************************************************** -->

<html class="no-focus">
<head>

    <meta charset="utf-8">

    <title><?= getPageTitle() ?></title>
    <?php
    if (!$iPhone) {
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    }
    ?>
    <meta name="robots" content="noindex, nofollow">

    <link rel='shortcut icon' href='<?= IMAGE ?>/<?= getFavicon()?>' type='image/x-icon' />
    <!-- Icons -->
    <!-- The following icons can be replaced with your own, they are used by desktop and mobile browsers -->

    <!-- END Icons -->

    <!-- Stylesheets -->
    <!-- Web fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400italic,600,700%7COpen+Sans:300,400,400italic,600,700&display=swap">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">


    <!-- Bootstrap and OneUI CSS framework -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
    <link rel="stylesheet" id="css-main" href="<?= CSS; ?>/oneui.css">

    <!-- Page JS Plugins CSS -->
    <link rel="preload" href="<?= SCRIPT; ?>/plugins/slick/slick.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="<?= SCRIPT; ?>/plugins/slick/slick.min.css">
    </noscript>

    <link rel="preload" href="<?= SCRIPT; ?>/plugins/slick/slick-theme.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="<?= SCRIPT; ?>/plugins/slick/slick-theme.min.css">
    </noscript>

    <link rel="preload" href="<?= CSS; ?>/tipped/tipped.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="<?= CSS; ?>/tipped/tipped.css">
    </noscript>

    <link rel="preload" href="<?= CSS; ?>/bellringing.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="<?= CSS; ?>/bellringing.css">
    </noscript>

    <link rel="preload" href="<?= SCRIPT; ?>/plugins/sweetalert/sweetalert.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="<?= SCRIPT; ?>/plugins/sweetalert/sweetalert.min.css">
    </noscript>

    <link rel="stylesheet" href="<?= SCRIPT; ?>/plugins/jquery-ui/jquery-ui.min.css">
    <link rel="stylesheet" href="<?= CSS; ?>/prettyPhoto.css" type="text/css" media="screen">
    <link rel="stylesheet" href="<?= SCRIPT; ?>/plugins/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css" type="text/css">



    <!-- You can include a specific file from css/themes/ folder to alter the default color theme of the template. eg: -->
    <!-- <link rel="stylesheet" id="css-theme" href="assets/css/themes/flat.min.css"> -->
    <!-- END Stylesheets -->
    <script async="false">
        rc = localStorage.getItem("ringcentral")
        //console.log("RC:"+rc)
        if (rc == "yes") {
            localStorage.removeItem("ringcentral")
            window.close()
        }
        var start = new Date();
    </script>
    <script>
        ! function(w, d, i, s) {
            function l() {
                if (!d.getElementById(i)) {
                    var f = d.getElementsByTagName(s)[0],
                        e = d.createElement(s);
                    e.type = "text/javascript", e.async = !0, e.src = "https://canny.io/sdk.js", f.parentNode.insertBefore(e, f)
                }
            }
            if ("function" != typeof w.Canny) {
                var c = function() {
                    c.q.push(arguments)
                };
                c.q = [], w.Canny = c, "complete" === d.readyState ? l() : w.attachEvent ? w.attachEvent("onload", l) : w.addEventListener("load", l, !1)
            }
        }(window, document, "canny-jssdk", "script");
    </script>
<!-- *************************************************************************************************************************************** -->

    <style>
        .rocell,
        .stcell,
        .dacell,
        .cucell,
        .phcell,
        .vecell,
        .tocell,
        .licell,
        .tycell {
            padding: 1px;
        }

        .table-medium {
            font-size: 14px
        }

        .table-small {
            font-size: 10px
        }

        .table-large {
            font-size: 18px
        }

        .shopnotice {
            resize: both;

        }

        .draggable {
            position: absolute;
            z-index: 100
        }

        .draggable-handler {
            cursor: pointer
        }

        .dragging {
            cursor: move;
            z-index: 200 !important
        }

        .nav-tabs {
            background-color: #FFFFFF;
            border: 1px gray solid;
        }

        .tab-content {
            background-color: #FFFFFF;
            color: #fff;
            padding: 5px
        }

        .nav-tabs>li>a {
            border: medium none;
        }

        .nav-tabs>li>a:hover {
            background-color: #336699 !important;
            font-weight: bold;
            border: medium none;
            border-radius: 0;
            color: #fff;
            cursor: pointer;
        }

        .nav-tabs>li.active>a {
            background-color: #F0AD4E !important;
            font-weight: bold;
            border: medium none;
            border-radius: 0;
            color: #fff;
            cursor: pointer;

        }

        .nav-tabs>li.active {
            background-color: #F0AD4E !important;
            font-weight: bold;
            border: medium none;
            border-radius: 0;
            color: #fff;
            cursor: pointer;

        }

        #newroalert {
            position: absolute;
            bottom: 0px;
            z-index: 9999;
            width: 80%;
            text-align: center;
            left: 10%;
            border: 2px red solid;
            border-radius: 5px;
            display: none;
        }

        .alert-danger {
            border: 1px red solid;
            border-radius: 5px;
        }

        #techaction {
            position: absolute;
            right: 20px;
        /*    top: 35px; */
            margin-top: 25px;
            border: 1px gray solid;
            border-radius: 4px;
            padding: 0px;
            width: 400px;
            height: 100px;
            z-index: 9999;
            display: <?php echo $showta; ?>;
            background-color: white;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-box-shadow: 2px 2px 2px 0px rgba(0, 0, 0, 0.75);
            -moz-box-shadow: 2px 2px 2px 0px rgba(0, 0, 0, 0.75);
            box-shadow: 2px 2px 2px 0px rgba(0, 0, 0, 0.75);
        }


        #h-nav-small {
            display: none
        }

        @media only screen and (max-width: 900px) {
            #techaction {
                display: none
            }

            #techactivities {
                display: block
            }

            #myshopnotice,
            #shopname,
            #resize-sm-btn,
            #resize-md-btn,
            #resize-lg-btn {
                display: none
            }
        }

        .iconbuttons {
            color: white;
            cursor: pointer;
            font-weight: normal;
            font-size: large;
            border: 1px silver solid;
            border-radius: 3px;
            text-align: center;
            width: 50px;
            padding: 1px 5px 1px 5px;
            margin: 2px
        }

        .iconbuttons:hover {
            color: red
        }


        @media only screen and (max-width: 420px) {
            .myframe {

                overflow: scroll;
                -webkit-overflow-scrolling: touch;
                -webkit-perspective: 0;

            }

            .scroll-wrapper {
                -webkit-overflow-scrolling: touch;
                overflow: scroll;
                width: 100%;


                /* important:  dimensions or positioning here! */
            }

        }

        @media only screen and (max-width: 640px) {
            body {
                font-size: 100%
            }

        }

        #techheader {
            text-align: center;
            color: white;
            background-color: #46C37B;
            position: fixed;
            width: 400px;
        }

        #closeta {
            float: right;
            margin-right: 30px;
            font-size: 18px;
            cursor: pointer
        }

        #maxta {
            float: right;
            margin-right: 5px;
            margin-top: 4px;
            cursor: pointer
        }

        #minta {
            float: right;
            margin-right: 5px;
            margin-top: 4px;
            cursor: pointer
        }

        #tatable {
            font-size: 9pt;
            margin-top: 20px
        }

        .betaclass {
            color: yellow !important;
            font-weight: bold !important;
            text-transform: uppercase !important;
            font-size: large !important
        }

        .sbalert {
            padding-bottom: 20px;
        }

        .sb-button {
            font-size: 14px;
            background-color: rgba(0, 0, 0, 0) !important;
            width: auto;
        }

        .sb-btn-group {
            padding: 3px !important;
        }

        .center {
            text-align: center !important;
        }

        .sb-bullhorn {
            font-size: 18px;

        }

        .beamer_icon.active,
        #beamerIcon.active {
            width: 10px !important;
            height: 10px !important;
            font-size: 8px !important;
            line-height: 10px !important;
        }

        #notification_badge {
            background: red;
            width: auto;
            height: auto;
            margin: 0;
            border-radius: 50%;
            position: absolute;
            top: -3px;
            right: -3px;
        }
    </style>
<!-- *************************************************************************************************************************************** -->


</head>
<!-- *************************************************************************************************************************************** -->

<!-- *************************************************************************************************************************************** -->

<body>
<?php include(COMPONENTS_PRIVATE_PATH."/shared/analytics.php"); ?>

    <!--<img src="<?= IMAGE ?>/loaderbig.gif" style="display:block" id="spinner">-->


    <!-- Page Container -->
    <div id="page-container" class="sidebar-l sidebar-o side-scroll header-navbar-fixed">
        <!-- Side Overlay-->
        <aside id="side-overlay">
            <!-- Side Overlay Scroll Container -->
            <div id="side-overlay-scroll">
                <!-- Side Header -->
                <div class="side-header side-content">
                    <!-- Layout API, functionality initialized in App() -> uiLayoutApi() -->
                    <button class="btn btn-default pull-right" type="button" data-toggle="layout" data-action="side_overlay_close">
                        <i class="fa fa-times"></i>
                    </button>
                    <span>
                        <img class="img-avatar img-avatar32" src="<?= IMAGE ?>/avatars/avatar10.jpg" alt="">
                        <span class="font-w600 push-10-l">Walter Fox</span>
                    </span>
                </div>
                <!-- END Side Header -->

            </div>
            <!-- END Side Overlay Scroll Container -->
        </aside>
        <!-- END Side Overlay -->
<!-- *************************************************************************************************************************************** -->

        <!-- Sidebar -->
        <nav id="sidebar">
            <!-- Sidebar Scroll Container -->
            <div id="sidebar-scroll">
                <!-- Sidebar Content -->
                <!-- Adding .sidebar-mini-hide to an element will hide it when the sidebar is in mini mode -->
                <div class="sidebar-content">
                    <!-- Side Header -->
                    <div class="side-header side-content bg-white-op">
                        <!-- Layout API, functionality initialized in App() -> uiLayoutApi() -->
                        <button class="btn btn-link text-gray pull-right hidden-md hidden-lg" type="button" data-toggle="layout" data-action="sidebar_close">
                            <i class="fa fa-times"></i>
                        </button>
                        <a class="h5 text-white" href="<?= COMPONENTS_PRIVATE ?>/wip/wip.php">
                            <?php getLogo() ?>
                        </a>
                    </div>
                    <!-- END Side Header -->

                    <!-- Side Content -->

                    <div class="side-content">

                        <h3 ondblclick="showIM()" style="color:white" class="center">Main Menu </h3>

                        <div class="btn-group sb-btn-group" role="group" aria-label="Menu Buttons">
                            <?php if ($showstatsonwip == 'YES') { ?><button type="button" onclick="showShopStats()" data-toggle="tooltip" title="Shop Stats" class="iconbuttons sb-button"><i class="fa fa-line-chart"></i></button><?php } ?>
                            <button type="button" onclick="confirmTechMode()" data-toggle="tooltip" title="Tech Mode" class="iconbuttons sb-button"><i class="fa fa-wrench"></i><i class="fa fa-user"></i></button>
                            <button type="button" id="imbutton" onclick="showIM()" data-toggle="tooltip" title="Messages" class="iconbuttons sb-button"> <i class="fa fa-commenting"></i></button>
                            <button type="button" id="reminderbutton" style="margin-left:4px;" onclick="changeIconColor()" data-toggle="tooltip" title="Reminders" class="iconbuttons sb-button"> <i class="fa fa-list-alt"></i></button>
                            <button type="button" id="notificationsbutton" style="margin-left:4px;" onclick="get_notifications('panel')" data-toggle="tooltip" title="Notifications" class="iconbuttons sb-button"> <i class="fa fa-bell"></i><span class="badge" id="notification_badge"></span></button>

                        </div>

                        <div id="alertmessagediv" style="display:none;color:red;background-color:white;font-weight:bold;text-align:center;border:1px silver solid;border-radius:3px;">*YOU HAVE A NEW IM*</div>
                        <div style="display:none;background-color:#3E4959;border-radius:5px;padding:5px;-webkit-box-shadow: 5px 5px 5px 0px rgba(181,181,181,1);
                            -moz-box-shadow: 5px 5px 5px 0px rgba(181,181,181,1);
                            box-shadow: 5px 5px 5px 0px rgba(181,181,181,1);" id="shopstats"></div>


                        <ul style="padding:5px;" class="nav-main">

                            <li>
                                <a onclick="loadWipByButton()" href="#"><i class="fa fa-wrench"></i><span class="sidebar-mini-hide">Work In Process</span></a>
                            </li>

                            <?php
                            if ($readonly == "no" && $_COOKIE['createro'] == 'yes') {
                            ?>
                                <li>
                                    <a class="nav-submenu" data-toggle="nav-submenu" href="#"><i class="fa fa-file-text-o"></i><span class="sidebar-mini-hide">Create RO</span></a>
                                    <ul>
                                        <li>
                                            <a onclick="location.href='<?= COMPONENTS_PRIVATE ?>/customer/customer-search.php'" href="#"><i class="fa fa-search"></i> Search</a>
                                        </li>
                                        <li>
                                            <a onclick="$('#scanmodal').modal('show')" href="#"><i class="fa fa-barcode"></i> Scan VIN</a>
                                        </li>
                                    </ul>
                                <li>
                                <?php
                            }
                                ?>
                                <li>
                                    <a href="https://<?= ROOT ?>/inspections/dashboard.php" target="_blank"><i class="fa fa-stethoscope"></i><span class="sidebar-mini-hide">BOSS Inspect<sup style="color:#F00"><b> NEW</b></sup></span></a>
                                </li>
                                <?php

                                if ($dashboardaccess == 'YES') {
                                ?>
                                    <li>
                                        <a href="<?= 'https://' . ROOT . '/dashboard.php'; ?>" target="_blank"><i class="fa fa-tachometer"></i><span class="sidebar-mini-hide">BOSS Board</span></a>
                                    </li>
                                <?php
                                }
                                ?>

                                <li>
                                    <a href="<?= COMPONENTS_PRIVATE ?>/customer/customer-find.php"><i class="fa fa-users"></i><span class="sidebar-mini-hide">Customers</span></a>
                                </li>


                                <?php if ($matco == 'yes') { ?>
                                    <li>
                                        <a href="<?= COMPONENTS_PRIVATE ?>/scan_tool_dashboard/index.php" target="_blank"><i class="fa fa-barcode"></i><span class="sidebar-mini-hide">Matco Scan Tool</span></a>
                                    </li>
                                <?php } ?>





                                <li>
                                    <a class="nav-submenu" data-toggle="nav-submenu" href="#"><i class="fa fa-calendar-check-o"></i><span class="sidebar-mini-hide">Schedule</span></a>
                                    <ul>
                                        <?php
                                        if ($shopid == "6176") {
                                        ?>
                                            <li>
                                                <a href="<?= SBP ?>daypilot/calendar.asp??type=Resources&sd=<?php echo date("Y-m-d"); ?>"><i class="fa fa-calendar-o"></i>Customer</a>
                                            </li>
                                        <?php
                                        } else {
                                        ?>
                                            <li>
                                                <a href="<?= COMPONENTS_PRIVATE ?>/calendar/calendar.php"><i class="fa fa-calendar-o"></i>Customer</a>
                                            </li>
                                        <?php
                                        }
                                        ?>
                                        <li>
                                            <a href="<?= COMPONENTS_PRIVATE ?>/empschedule/calendar.php"><i class="fa fa-calendar"></i>Employee</a>
                                        </li>
                                    </ul>

                                </li>
                                <!-- <li>
                                    <a href="<?= COMPONENTS_PRIVATE ?>/quotes/quotes.php"><i class="fa fa-calculator"></i><span class="sidebar-mini-hide">Quotes</span></a>
                                </li> -->
                                <?php //if($quotesbeta=='yes'){?>
                                <li>
                                    <a href="https://<?= ROOT ?>/newquotes/quotes.php"><i class="fa fa-calculator"></i><span class="sidebar-mini-hide">Quotes</span></a>
                                </li>
                                <?php //}?>
                                <li>
                                    <a href="<?= COMPONENTS_PRIVATE ?>/dispatch/dispatch.php"><i class="fa fa-share-square"></i><span class="sidebar-mini-hide">Dispatch</span></a>
                                </li>
                                <li>
                                    <a href="<?= COMPONENTS_PRIVATE ?>/ro/findro.php"><i class="fa fa-search"></i><span class="sidebar-mini-hide">Find RO</span></a>
                                </li>
                                <li>
                                    <a href="<?= COMPONENTS_PRIVATE ?>/history/history.php"><i class="fa fa-history"></i><span class="sidebar-mini-hide">History</span></a>
                                </li>
                                <li id="cores">
                                    <a href="<?= COMPONENTS_PRIVATE ?>/cores/cores.php"><i class="fa fa-exclamation-triangle"></i><span class="sidebar-mini-hide">Cores To Return</span></a>
                                </li>
                                <li>
                                    <a href="<?= COMPONENTS_PRIVATE ?>/follow/main.php"><i class="fa fa-user"></i><span class="sidebar-mini-hide">Follow Up</span></a>
                                </li>
                                <li>
                                    <a href="<?= COMPONENTS_PRIVATE ?>/po/po.php"><i class="fa fa-sign-in"></i><span class="sidebar-mini-hide">Receive PO</span></a>
                                </li>

                                <?php if ($EditInventory == "YES") { ?>
                                    <li>
                                        <a href="<?= COMPONENTS_PRIVATE ?>/inventory/inventory.php"><i class="fa fa-folder-open"></i><span class="sidebar-mini-hide">Inventory</span></a>
                                    </li>
                                <?php }
                                if ($reportsaccess == "YES") { ?>
                                    <li>
                                        <!-- <a href="reports.php"><i class="fa fa-clipboard"></i><span class="sidebar-mini-hide">Reports</span></a> -->
                                        <a href="<?= COMPONENTS_PRIVATE ?>/reports/reports.php"><i class="fa fa-clipboard"></i><span class="sidebar-mini-hide">Reports</span></a>


                                    </li>
                                <?php }
                                if ($accountingaccess == "YES") { ?>
                                    <li>
                                        <a href="<?= COMPONENTS_PRIVATE ?>/accounting/default.php"><i class="fa fa-calculator"></i><span class="sidebar-mini-hide">Accounting</span></a>

                                    </li>
                                <?php } ?>
                                <li>
                                    <a href="<?= INTEGRATIONS ?>/happyfox/jwtlogin.php" target="_blank"><i class="fa fa-life-ring"></i><span class="sidebar-mini-hide">Support</span></a>
                                </li>
                                <li>
                                    <!-- adding pages path -->
                                    <a href="<?= PAGES ?>videos.php"><i class="fa fa-youtube"></i><span class="sidebar-mini-hide">Videos</span></a>
                                </li>

                                <?php if ($sbpackage != 'Trial') { ?>
                                    <li>
                                        <a href="<?= $cannyurl ?>" target="_blank"><i class="fa fa-comments-o"></i><span class="sidebar-mini-hide">Feedback</span></a>
                                    </li>
                                <?php
                                }
                                $add_days = 6;
                                $enddate = date('Y-m-d', strtotime($date) + (24 * 3600 * $add_days));
                                if (date("Y-m-d") < $enddate) {
                                ?>
                                    <!-- <li>
                                <a class="betaclass" href="beta.php"><i class="fa fa-asterisk"></i><span class="sidebar-mini-hide">BETA Testers!</span></a>
                            </li> -->
                                <?php
                                }
                                if ($settingsaccess == "YES") { ?>
                                    <li>
                                        <a href="<?= COMPONENTS_PRIVATE ?>/settings/main.php"><i class="fa fa-cog"></i><span class="sidebar-mini-hide">Settings</span></a>
                                    </li>
                                <?php } ?>
                        </ul>
                        <?php
                        if ($rocount <= 10) {
                        ?>
                            <button onclick="openQuickStartVideo(this)" data-embed="<?= $video_url ?>" class="btn btn-success btn-lg">Quick Start Video</button>
                        <?php
                        }
                        ?>

                        <?php
                        if (($shopid <= 5623 && $shopid != 'demo' && $shopid != '1734') || $shopid == "5974" || $shopid == "6517" || $shopid == '6942' || $shopid == '14083') {
                            if (strtolower($masterinterface) == "classic") {
                        ?>
                                <br><br><button onclick="location.href='<?= SBP ?>wip.asp?interface=1&override=yes'" class="btn btn-info btn-lg">Classic Shop Boss</button>
                        <?php
                            }
                        }
                        ?>
                    </div>
                    <!-- END Side Content -->
                </div>
                <!-- Sidebar Content -->
                <?php
                //echo gethostname();
                //echo "<BR>".$lb;
                ?>
            </div>
            <!-- END Sidebar Scroll Container -->
        </nav>
        <!-- END Sidebar -->
<!-- *************************************************************************************************************************************** -->



<!-- *************************************************************************************************************************************** -->

        <!-- Header -->
        <header id="header-navbar" class="content-mini content-mini-full">

            <?php if ($failedpayment == 'yes') { ?><div class="alert alert-danger text-center"><a href="#" class="close" onclick="resetTop()" data-dismiss="alert" aria-label="close">&times;</a>ACTION REQUIRED - The card on file for your subscription failed, please click the link to enter new payment information - <a href="<?= $paybalancelink ?>" target="_blank">Click here to update.</a></div><?php } ?>

            <?php if ($showQuickVideoBanner) { ?>
                    <!-- TRIAL SHOW QUICK START VIDEO -->
                <div class="alert alert-success text-center">
                <a href="#" data-embed="<?= $video_url ?>" onclick="openQuickStartVideo(this)" data-days="<?= $days_started ?>">Click to View Your Quick Start Video</a>
                </div>
            <?php } else { ?>

            <?php if (!empty($capitalarr)) { ?><div class="alert alert-success text-center"><a href="#" title="This will hide the alert for next 7 days" class="close" onclick="hideCapital()" data-dismiss="alert" aria-label="close">&times;</a><a href="<?= $capitalarr['url'] ?>" target='_blank'><?= $capitalarr['message'] ?></a></div><?php } ?>

            <?php
            }
            ?>

            <div id="trialdiv">
                <!-- Header Navigation Right -->
                <ul class="nav-header pull-right">
                    <li>
                        <div style="overflow:hidden;resize:both" id="shopnotice">
                            <?php echo "<span id='shopname'>" . $_COOKIE['shopname'] . " #" . $shopid . " " . $_COOKIE['username'] . "</span>";
                            echo "&nbsp;&nbsp;<span style='cursor:pointer;' data-canny-changelog id='cannyButton'><i class='fa fa-history sb-bullhorn'></i></span>";
                            echo "&nbsp;&nbsp;<span id='beamerButton'><i class='fa fa-bullhorn sb-bullhorn'></i></span><a class='btn btn-primary btn-sm btn-logoff' href='https://" . $_SERVER['SERVER_NAME'] . "/logoff.php'><i class='fa fa-sign-out'></i><span class='sidebar-mini-hide'>Logoff</span></a>"; ?>
                        </div>
                    </li>
                </ul>
                <div id="techaction">
                    <div id="techheader" style="">
                        <i class="fa fa-times-circle" id="closeta" onclick="closeTA()"></i>
                        <i class="fa fa-window-maximize" id="maxta" onclick="maxTA()"></i>
                        <i class="fa fa-window-minimize" id="minta" onclick="minTA()"></i>

                        Today's Tech activities
                    </div>
                    <div id="techresults">
                        <table id="tatable" class="table table-condensed table-striped table-hover">
                            <?php
                            $tastartdate = date("Y-m-d") . " 00:00:00";
                            $stmt = "select startdate,message from alerts where shopid = '$shopid' and startdate >= '$tastartdate' order by startdate desc limit 200";
                            if ($query = $conn->prepare($stmt)) {
                                $query->execute();
                                $result = $query->get_result();
                                while ($row = $result->fetch_assoc()) {
                                    $sd = strtotime($row['startdate']);
                                    $sd = date("m/d/Y h:i:s A", $sd);
                                    $msg = $row['message'];
                                    $splocation = strrpos($msg, " ");
                                    $splocation = strlen($msg) - $splocation;
                                    //echo $splocation;
                                    $thisroid = trim(substr($msg, -$splocation));
                                    $msg = str_replace("RO " . $thisroid, "<a style='font-weight:bold' href='" . COMPONENTS_PRIVATE . "/ro/ro.php?roid=" . $thisroid . "'>RO " . $thisroid . "</a>", $msg);
                                    ?>
                                    <tr>
                                        <td><?php echo $sd; ?> - <?php echo $msg; ?></td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </table>
                    </div>
                </div>
                <!-- END Header Navigation Right -->

                <!-- Header Navigation Left -->

                <ul class="nav-header pull-left">
                    <li class="hidden-md hidden-lg">
                        <!-- Layout API, functionality initialized in App() -> uiLayoutApi() -->
                        <button class="btn btn-default" data-toggle="layout" data-action="sidebar_toggle" type="button">
                            <i class="fa fa-navicon"></i>
                        </button>
                    </li>
                    <li class="hidden-xs hidden-sm">
                        <!-- Layout API, functionality initialized in App() -> uiLayoutApi() -->
                        <button class="btn btn-default" data-toggle="layout" id="close-sidebar" data-action="sidebar_mini_toggle" type="button">
                            <i class="fa fa-bars"></i>
                        </button>
                    </li>
                    <li>
                        <!-- Opens the Apps modal found at the bottom of the page, before including JS code -->
                        <button style="display:none" class="btn btn-default pull-right" data-toggle="modal" data-target="#apps-modal" type="button">
                            <i class="si si-grid"></i>
                        </button>
                    </li>
                    <li class="visible-xs">
                        <!-- Toggle class helper (for .js-header-search below), functionality initialized in App() -> uiToggleClass() -->
                        <button class="btn btn-default" data-toggle="class-toggle" data-target=".js-header-search" data-class="header-search-xs-visible" type="button">
                            <i class="fa fa-search"></i>
                        </button>
                    </li>
                    <li>
                        <?php
                        if ($failedpayment == 'no' && empty($capitalarr) && !$showQuickVideoBanner && strlen($shopnotice) > 0) {
                            echo '<div contenteditable="' . ($ChangeNotice == 'YES' ? 'true' : 'false') . '" id="' . ($ChangeNotice == 'YES' ? 'myshopnotice' : '') . '" class="shopnotice">' . $shopnotice . '</div>';
                        }
                        ?>
                    </li>




                </ul>

                <!-- END Header Navigation Left -->
            </div>
        </header>
        <!-- END Header -->
<!-- *************************************************************************************************************************************** -->




        <style>
            .reducedWatermark {
                display: none;
            }

            .free {
                display: none;
            }
        </style>



<!-- *************************************************************************************************************************************** -->

        <!-- Main Container -->
        <main id="main-container" <?= $failedpayment == 'yes' || !empty($capitalarr) || $showQuickVideoBanner ? "style='margin-top: 70px;'" : '' ?>>
            <ul id="horizontal-nav" style="margin-left:20px" class="nav nav-tabs">
                <li onclick="<?= $workflownewtab == 'yes' ? "openWorkflow()" : "changeWip('kanban')" ?>" id="" class="btn btn-sm btn-success">WORKFLOW</li>
                <li onclick="loadWipByButton()" id="" class="btn btn-sm btn-primary">LOAD WIP (RE-SORT)</li>
                <?php

                if($sortwipbysa=='yes')
                {
                    // get a list of the current advisors
                    $stmt = "select distinct writer from repairorders where shopid = '$shopid' and `status` != 'CLOSED' and ROType != 'No Approval' UNION select distinct writer from ps where shopid = '$shopid' and `status` != 'CLOSED' AND `status` != 'dead'";

                    if ($query = $conn->prepare($stmt)){
                        $query->execute();
                        $r = $query->get_result();
                        while ($rs = $r->fetch_array()){
                            $writer = str_replace(" ","_",$rs['writer']);
                            echo '<li id="'.$writer.'" class=""><a style="padding:5px;" href="#" onclick="changeWip(\''.$writer.'\')">'.ucwords(strtolower($rs['writer'])).'</a></li>';
                        }
                    }
                }

                ?>
                <li style="display:none" id="wipbystatustab" class="active"><a style="padding:5px;" href="#" onclick="changeWip('wipbystatustab')">SORT WIP BY STATUS</a></li>
                <li style="display:none" id="wipbytypetab"><a style="padding:5px" href="#" onclick="changeWip('wipbytypetab')">SORT WIP BY TYPE</a></li>
                <li style="display:none" id="wipbyrotab"><a style="padding:5px" href="#" onclick="changeWip('wipbyrotab')">SORT WIP BY RO#</a></li>
                <li style="display:none" id="wipbydatetab" style="left: 0px; top: 0px; width: 123px"><a style="padding:5px" href="#" onclick="changeWip('wipbydatetab')" data-toggle="tabs">SORT WIP BY DATE</a></li>
                <li id="timeclocktab" class="btn btn-sm btn-warning" style="left: 0px; top: 0px" onclick="changeWip('timeclocktab')">EMPLOYEE TIME CLOCK</li>
                <?php
                if ($motor=='yes' && ($motorfull == "yes" || $motorest == "yes")) {
                ?>
                    <li onclick="changeWip('motortab')" class="btn btn-info btn-sm" id="motortab">MOTOR CLASSIC</li>
                    <!-- <li onclick="loadNewMotor()" class="btn btn-primary btn-sm" style="width: 150px" id="newmotortab"><img src="https://<?php echo $_SERVER['HTTP_HOST']; ?>/sbpi2/assets/img/icons/motor_driven_white.svg"></li> -->
                <?php
                }
                ?>
                <li onclick="changeWip('smstab')" class="btn btn-danger btn-sm" id="smstab">LIVE TEXT</li>
                <li id="techactivities" class="btn btn-success btn-sm" onclick="openTA()" style="display:<?php echo $showtabtn; ?>;" id="smstab">TECH ACTIVITIES</li>
                <li style="font-size:x-small;font-weight:bold;display:none" id="turnoffta">&nbsp; &lt;- You can disable this<br>&nbsp; in Settings-&gt;Misc</li>
            </ul>
            <section id="wipmainrow" style="background-color:white" class="col-lg-12 scrollable">
                <div style="">
                    <iframe class="myframe" scrolling="yes" id="myframe" style="overflow: scroll;-webkit-overflow-scrolling: touch;overflow-x:scroll;width:100%"></iframe>
                    <iframe scrolling="yes" style="position:fixed;top:1%;left:0.5%;width:99%;height:98%;border:2px black solid;background-color:white;display:none;z-index:9999;padding:2px;" id="motorframe"></iframe>
                </div>
            </section>
        </main>
        <!-- END Main Container -->
<!-- *************************************************************************************************************************************** -->

        <!-- Footer -->
        <!-- END Footer -->
    </div>
    <!-- END Page Container -->

    <!-- *************************************************************************************************************************************** -->

    <div id="quickStartModal" class="modal fade">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Quick Start</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe id="youtubeFrame" width="560" height="315" src="https://www.youtube.com/embed/_fpZWTqjhSc" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-md btn-default" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div id="apptmodal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <ul class="block-options">
                            <li>
                                <button data-dismiss="modal" type="button"><i class="si si-close"></i></button>
                            </li>
                        </ul>
                        <h3 id="tctitle" class="block-title">New Appointment Scheduled</h3>
                    </div>
                    <div class="block-content">
                        <div class="row">
                            <div class="col-md-12">
                                <h3>Click an appointment to view it in the Schedule. Click Mark Read to mark all of them as read</h3>
                                <table class="table table-condensed table-striped table-header-bg">
                                    <thead>
                                        <tr>
                                            <td>Date/Time</td>
                                            <td>First</td>
                                            <td>Last</td>
                                            <td>Year</td>
                                            <td>Make</td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    </thead>
                                    <tbody id="apptbody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;" class="modal-footer">
                    <button class="btn btn-md btn-primary" type="button" onclick="dismissAllAppt()">Mark Read</button>
                    <button class="btn btn-md btn-default" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="pmtmodal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <ul class="block-options">
                            <li>
                                <button data-dismiss="modal" type="button"><i class="si si-close"></i></button>
                            </li>
                        </ul>
                        <h3 id="tctitle" class="block-title">REMOTE Payments Received</h3>
                    </div>
                    <div class="block-content">
                        <div class="row">
                            <div class="col-md-12">
                                <h3>You have received these remote payments. Click Mark Read to mark all of them as read</h3>
                                <table class="table table-condensed table-striped table-header-bg">
                                    <thead>
                                        <tr>
                                            <td>Date</td>
                                            <td>RO</td>
                                            <td>Customer</td>
                                            <td>Amount</td>
                                        </tr>
                                    </thead>
                                    <tbody id="pmtbody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;" class="modal-footer">
                    <button class="btn btn-md btn-primary" type="button" onclick="dismissAllRemotePayments()">Mark Read</button>
                    <button class="btn btn-md btn-default" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="confirmapptmodal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <ul class="block-options">
                            <li>
                                <button data-dismiss="modal" type="button"><i class="si si-close"></i></button>
                            </li>
                        </ul>
                        <h3 id="tctitle" class="block-title">Confirm Appointment Scheduled</h3>
                        <input type="hidden" id="appointment_id">
                    </div>
                    <div class="block-content">
                        <div class="row">
                            <div class="col-md-12">
                                <div style="margin-bottom:20px;" class="col-md-12">
                                    <div class="form-material floating">
                                        <input class="form-control sbp-form-control" style="padding:20px;" tabindex="1" id="emailmessageaddress" name="emailmessageaddress" type="text" value="">
                                        <label style="-webkit-transform:translateY(-24px);transform:translateY(-24px);-ms-transform:translateY(-24px)" id="emailmessageaddresslabel" for="emailmessageaddress">Email Address</label>
                                    </div>
                                </div>
                                <div style="margin-bottom:20px;" class="col-md-12">
                                    <div class="form-material floating">
                                        <input class="form-control sbp-form-control" style="padding:20px;" tabindex="1" id="emailmessagesubject" name="emailmessagesubject" type="text" value="Your scheduled appointment with <?php echo $_COOKIE['shopname']; ?>">
                                        <label for="emailmessagesubject">Subject</label>
                                    </div>
                                </div>
                                <div style="margin-bottom:20px;" class="col-md-12">
                                    <div class="form-material floating">
                                        <textarea class="form-control sbp-form-control" style="padding:20px;" tabindex="1" id="emailmessagemessage" name="emailmessagemessage">
                        <?php

                        if ($shopid == "7296") {
                            echo "Thank You For Scheduling Online, Please Wait For A Confirmation Email From Us Confirming Your Requested Appointment.   *** Due to Covid 19 and for the safety of our employees and the customer we can not accommodate waiting appointments! ***";
                        } else {
                            echo "Thanks for setting your appointment with us online. We are able to accommodate the time slot you requested and look forward to seeing you then.";
                        }

                        ?>

                                            </textarea>
                                        <label for="emailmessagemessage">Message</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;" class="modal-footer">
                    <button class="btn btn-md btn-primary" type="button" onclick="sendEmailMessage()">Send Message</button>
                    <button class="btn btn-md btn-default" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="shopalertsmodal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <ul class="block-options">
                            <li>
                                <button data-dismiss="modal" type="button"><i class="si si-close"></i></button>
                            </li>
                        </ul>
                        <h3><img src="<?= IMAGE ?>/shopboss-logo-nut.png" style="max-height:25px" alt=""> SHOP ALERTS</h3>
                    </div>
                    <div class="block-content">
                        <div class="row">
                            <div id="shopalertsdiv" class="col-md-12">
                                <?php
                                $msgids = "";
                                $rowcntr = 1;
                                $sadate = date("Y-m-d H:m:s", strtotime("-60 days"));
                                $stmt = "select id,msg from shopalerts where shopid = '$shopid' and userid = '$empid' and `show` = 'yes' order by ts desc";
                                //echo $stmt;
                                $shopmsgcntr = 0;
                                if ($query = $conn->prepare($stmt)) {
                                    $query->execute();
                                    $result = $query->get_result();
                                    while ($row = $result->fetch_array()) {
                                        $msgcookieid = "msg" . $row['id'];
                                        if (!isset($_COOKIE[$msgcookieid])) {
                                            $msgids .= $row['id'] . "|";
                                            $rowremainder = $rowcntr % 2;
                                            if ($rowremainder != 0) {
                                                $bgcolor = "#ffffff";
                                            } else {
                                                $bgcolor = "#f4ffff";
                                            }
                                            $shopmsgcntr += 1;
                                ?>
                                            <div id="shopmsg<?php echo $row['id']; ?>" class="alertdivs" style="padding:15px;border:1px silver solid;border-radius:5px;margin:7px;background-color:<?php echo $bgcolor; ?>">
                                                <span style="float:right" onclick="hideMsg(<?php echo $row['id']; ?>)" class="btn btn-sm btn-danger">Mark as Read</span>
                                                <?php echo $row['msg']; ?>
                                            </div>
                                <?php
                                            $shopmsgcntr += 1;
                                        }
                                    }
                                    $query->close();
                                }
                                if ($shopmsgcntr == 0) {
                                    echo "<div style='padding:20px;text-align:center'><h4>You have no un-read alerts.  To see previous alerts, click the Show All button</h4></div>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;" class="modal-footer">
                    <button class="btn btn-md btn-default" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="immodal" class="modal fade" id="modal-large" tabindex="-1" role="dialog" aria-hidden="true">
        <input id="customerid" type="hidden">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <ul class="block-options">
                            <li>
                                <button data-dismiss="modal" type="button"><i class="si si-close"></i></button>
                            </li>
                        </ul>
                        <h3 class="block-title">Instant Messages</h3>
                    </div>
                    <div class="block-content">
                        <div class="row">
                            <div id="imlist" style="max-height:500px;overflow-y:scroll" class="col-md-12">

                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="col-md-4">To:<br>
                                    Press Cntrl + Click to select multiple
                                </div>
                                <div class="col-md-8">
                                    <select id="emps" multiple="multiple" class="form-control">
                                        <option value="none">Select</option>
                                        <?php
                                        $stmt = "select employeefirst, employeelast from employees where shopid = ? and active = 'yes' and concat(employeefirst,' ',employeelast) != ?";
                                        if ($query = $conn->prepare($stmt)) {
                                            $query->bind_param("ss", $shopid, $usr);
                                            $query->execute();
                                            $result = $query->get_result();
                                            $select = "";
                                            while ($row = $result->fetch_array()) {
                                                $select .= "<option value='" . $row['employeefirst'] . " " . $row['employeelast'] . "'>" . $row['employeefirst'] . " " . $row['employeelast'] . "</option>";
                                            }
                                            echo $select;
                                            $query->close();
                                        }

                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="col-md-4">Message:</div>
                                <div class="col-md-8"><textarea id="msgboxsend" class="form-control"></textarea></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;" class="modal-footer">
                    <button class="btn btn-warning btn-md" type="button" onclick="sendMessage()">Send Message</button>
                    <button class="btn btn-default btn-md" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="remindermodal" class="modal fade" id="modal-large" tabindex="-1" role="dialog" aria-hidden="true">
        <input id="customerid" type="hidden">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <ul class="block-options">
                            <li>
                                <button data-dismiss="modal" type="button"><i class="si si-close"></i></button>
                            </li>
                        </ul>
                        <h3 class="block-title">Reminders / To Do List</h3>
                    </div>
                    <div class="block-content">
                        <div id="reminderlist" class="row" style="max-height:400px;overflow-y:auto">
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;" class="modal-footer">
                    <button class="btn btn-primary btn-md" type="button" data-toggle="modal" data-target="#addremindermodal" data-dismiss="modal">Add Reminder</button>
                    <button class="btn btn-default btn-md" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="notificationmodal" class="modal fade" id="modal-large" tabindex="-1" role="dialog" aria-hidden="true">
        <input id="customerid" type="hidden">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <ul class="block-options">
                            <li>
                                <button data-dismiss="modal" type="button"><i class="si si-close"></i></button>
                            </li>
                        </ul>
                        <h3 class="block-title">Notifications</h3>
                    </div>
                    <div class="block-content row" style="max-height:400px;overflow-y:auto">
                        <div id="notificationlist" class="col-md-12">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="addremindermodal" class="modal fade" id="modal-large" tabindex="-1" role="dialog" aria-hidden="true">
        <input id="customerid" type="hidden">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <ul class="block-options">
                            <li>
                                <button data-dismiss="modal" type="button"><i class="si si-close"></i></button>
                            </li>
                        </ul>
                        <h3 class="block-title">Add a New Reminder</h3>
                    </div>
                    <div class="block-content">
                        <h3>This is your personal Reminder list</h3>
                        <p>You can add any kind of reminder you like. When a Reminder is due, the icon will flash orange and white. If a Reminder is overdue, it will stay orange</p>
                        <div class="row" style="padding:5px;">
                            <br>
                            <strong>Enter your Reminder here. Maximum 300 characters</strong>
                            <textarea maxlength="300" class="form-control" id="newreminder"></textarea><br>
                            <span id="maxchar">300 remaining</span>
                        </div>
                        <div class="row" style="padding:5px">
                            <strong>Select a date and time</strong>
                            <input type="text" class="form-control" id="newreminderdate"><br>
                            <br>
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;" class="modal-footer">
                    <button class="btn btn-primary btn-md" type="button" onclick="saveReminder()">Save Reminder</button>
                    <button class="btn btn-default btn-md" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="scanmodal" class="modal fade" id="modal-large" tabindex="-1" role="dialog" aria-hidden="true">
        <input id="customerid" type="hidden">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <ul class="block-options">
                            <li>
                                <button data-dismiss="modal" type="button"><i class="si si-close"></i></button>
                            </li>
                        </ul>
                        <h3 class="block-title">Scan VIN</h3>
                    </div>
                    <div id="vehinfo" class="block-content"></div>
                    <div class="block-content">
                        <div class="row">
                            <div id="vehiclemaininfo" style="text-align:center" class="col-md-12">
                                <p>If this is your first time scanning a VIN, please install the app on your phone or tablet by clicking the appropriate button below. If you have already installed
                                    the app, click the Scan VIN button. After scanning, you can retrieve your scanned VIN by clicking Retrieve Scanned VIN button</p>
                                <b>NOTE: The VIN Scan App is only available on Android or Apple tablets and phones. Kindle Fire and Amazon Fire are not supported.</b>
                                <p id="scannedvins"></p>
                                <button style="width:250px;" class="btn btn-primary btn-lg" type="button" onclick="launchScanner()">SCAN VIN</button>
                                <button style="width:250px;" class="btn btn-success btn-lg" type="button" onclick="getScannedVins()">
                                    Retrieve Scanned VIN</button>
                                <br><br>
                            </div>
                        </div>

                    </div>
                </div>
                <div style="margin-top:20px;" class="modal-footer">
                    <button class="btn btn-warning btn-sm" onclick="launchBossVin()" type="button">BOSS Vin</button>
                    <button class="btn btn-warning btn-sm" type="button" onclick="installAndroid()">Install Android App</button>
                    <button class="btn btn-warning btn-sm" type="button" onclick="installApple()">Install Apple App</button>
                    <button class="btn btn-default btn-sm" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div id="bossvinscanmodal" class="modal fade" id="modal-large" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <ul class="block-options">
                            <li>
                                <button data-dismiss="modal" type="button"><i class="si si-close"></i></button>
                            </li>
                        </ul>
                        <h3 class="block-title">New VIN Scan</h3>
                    </div>
                    <div id="vehinfo" class="block-content"></div>
                    <div class="block-content">
                        <div class="row">
                            <div style="text-align:center" class="col-md-12">
                                <div id="pictureslot" style="height: 200px;display: none;margin-bottom: 20px;"></div>
                                <div class="btn-group" style="text-align: center;">
                                    <button type="button" onclick="$('#ocrimage').click()" class="btn btn-warning">Upload Picture</button>
                                </div>
                                <form name="ocrform" enctype="multipart/form-data" style="display: none;">
                                    <input type="file" class="form-control" name="ocrimage" id="ocrimage" accept="image/png,image/jpeg,image/png,image/gif,image/bmp">
                                </form>
                                <br><br>
                            </div>
                        </div>

                    </div>
                </div>
                <div style="margin-top:20px;" class="modal-footer">
                    <button class="btn btn-success" id="btn-ocrscan" type="button">Scan</button>
                    <button class="btn btn-default" type="button" onclick="$('#bossvinscanmodal').modal('hide')">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div id="alfredmodal" class="modal fade" id="modal-large" tabindex="-1" role="dialog" aria-hidden="true">
        <input id="customerid" type="hidden">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <ul class="block-options">
                            <li>
                                <button data-dismiss="modal" type="button"><i class="si si-close"></i></button>
                            </li>
                        </ul>
                        <h3 class="block-title">Alfred Help Commands</h3>
                    </div>
                    <div id="vehinfo" class="block-content"></div>
                    <div class="block-content">
                        <div class="row">
                            <div id="alfredcommandlist" style="text-align:left;font-size:large" class="col-md-12">
                                <ul>
                                    <li>"Create Repair Order" - start a new RO</li>
                                    <li>"Find Repair Order [say the RO number or name to find]" - Filter WIP List</li>
                                    <li>"Open Repair Order [say the RO number to open]" - Open a Repair Order</li>
                                    <li>"Manage Customers" - go to customer list</li>
                                    <li>"WIP (whip)" - go to WIP List</li>
                                    <li>"Cancel Help" - Close this window</li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>
                <div style="margin-top:20px;" class="modal-footer">
                    <button class="btn btn-default btn-sm" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="changepassmodal" class="modal fade" id="modal-large" tabindex="-1" role="dialog" aria-hidden="true" style="z-index:99999999999">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="block block-themed block-transparent remove-margin-b">
                    <div class="block-header bg-primary-dark">
                        <h3 class="block-title">Change Password</h3>
                    </div>
                    <div class="block-content">
                        <p>In an effort to enhance security and protect user data, ShopBoss is requiring all users to update their passwords.<br><br>Password should be minimum eight characters long, at least one letter, at least one number.<br><br><b>If you have any issues after changing your password, clear your browser cache and cookies.</b> <br><br>Please contact support@shopbosspro.com if you have any questions. </p>
                        <div class="row" style="padding:5px;">
                            <strong>New Password</strong>
                            <input class="form-control sbp-form-control" tabindex="1" id="newpass" autocomplete="off" name="newpass" type="password">
                        </div>
                        <div class="row" style="padding:5px">
                            <strong>Confirm New Password</strong>
                            <input class="form-control sbp-form-control" tabindex="2" id="cnewpass" autocomplete="off" name="cnewpass" type="password">
                            <br>
                        </div>
                        <div class="row" id="changepassmsg" style="padding:5px;color: red;">
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;" class="modal-footer">
                    <a class="btn btn-primary btn-sm pull-left" href="<?= COMPONENTS_PUBLIC ?>/login/logoff.php">Logoff</a>
                    <button class="btn btn-success btn-sm" id="btn-change-pass" onclick="changepass()" type="button">Save</button>
                </div>
            </div>
        </div>
    </div>


    <div style="display:none;position:absolute;top:0px;width:60%;left:20%;z-index:99999" id="overcount" class="alert alert-danger alert-dismissable">
        <b>WARNING!</b> You have over 1000 OPEN repair orders. This will cause your Work In Process list to load very slowly and reports will be inaccurate. Please close all repair orders that work
        has been completed on. If you have questions, please contact support<br>
        <button style="float:right" class="btn btn-sm btn-danger" onclick="$('#overcount').hide()" type="button">Close Alert</button>
    </div>

    <input type="hidden" id="opencounter" value="">

<!-- *************************************************************************************************************************************** -->



    <script src="<?= SCRIPT; ?>/core/jquery.min.js"></script>
    <script defer src="<?= SCRIPT; ?>/tipped.js"></script>
    <script defer src="<?= SCRIPT; ?>/core/bootstrap.min.js"></script>

    <!-- OneUI Core JS: jQuery, Bootstrap, slimScroll, scrollLock, Appear, CountTo, Placeholder, Cookie and App.js -->
    <script defer src="<?= SCRIPT; ?>/core/jquery.slimscroll.min.js"></script>
    <script defer src="<?= SCRIPT; ?>/core/jquery.scrollLock.min.js"></script>
    <script defer src="<?= SCRIPT; ?>/core/jquery.appear.min.js"></script>
    <script defer src="<?= SCRIPT; ?>/core/jquery.countTo.min.js"></script>
    <script defer src="<?= SCRIPT; ?>/core/jquery.placeholder.min.js"></script>
    <script defer src="<?= SCRIPT; ?>/core/js.cookie.min.js"></script>
    <script defer src="<?= SCRIPT; ?>/plugins/sweetalert/sweetalert.min.js"></script>
    <script defer src="<?= SCRIPT; ?>/plugins/jquery-ui/jquery-ui.js"></script>
    <script defer src="<?= SCRIPT; ?>/plugins/moment/moment.js"></script>
    <script defer src="<?= SCRIPT; ?>/plugins/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script defer src="<?= SCRIPT; ?>/emodal.js"></script>
    <script defer src="<?= SCRIPT; ?>/app.js"></script>
    <script defer src="<?= SCRIPT ?>/jquery.floatThead.js"></script>
    <script defer type="text/javascript" src="<?= SCRIPT ?>/jquery.prettyPhoto.js"></script>
    <script defer src="//cdnjs.cloudflare.com/ajax/libs/annyang/2.6.0/annyang.js"></script>
    <script defer src="//cdnjs.cloudflare.com/ajax/libs/SpeechKITT/0.3.0/speechkitt.min.js"></script>
    <script src="<?= SCRIPT ?>/ion.sound.min.js"></script>

    <!-- Page Plugins -->

    <!-- Page JS Code
        <script src="<?= SCRIPT; ?>/pages/base_pages_dashboard.js"></script>-->
    <script>
        storednotifications = ''

        jQuery(function() {
            // Init page helpers (Slick Slider plugin)
            App.initHelpers('slick');
            if (document.getElementById('myshopnotice')) {
                var editor = document.getElementById('myshopnotice');
                editor.isContentEditable;
                editor.contentEditable = true;
            }
            $('#newsb').magnificPopup({
                type: 'iframe'
            });
            checkSMS()

            <?php if ($empid != "Admin") { ?>
                checkPassChange()
            <?php } ?>

            window.addEventListener('touchstart', function() {
                localStorage.setItem("touch", "yes");
            });

            $('#newreminder').keyup(function() {
                maxchar = 300
                thischar = $(this).val().length
                remchar = maxchar - thischar
                $('#maxchar').html(remchar + " remaining")
            });

            $('#newreminderdate').datetimepicker({
                inline: true,
                sideBySide: true
            });

            <?php
            if ($shopid == "6263") {
            ?>
                if (annyang) {

                    var commands = {
                        '*command': alfredCommands,
                    }

                    // Add our commands to annyang
                    annyang.addCommands(commands);

                    annyang.start();

                }
            <?php
            }
            ?>

        });


        function openWorkflow() {

            window.open("<?= COMPONENTS_PRIVATE ?>/workflow/board.php");

        }

        function dismissAllRemotePayments() {

            $.ajax({
                data: "t=dismissall&shopid=<?php echo $shopid; ?>",
                url: "<?= COMPONENTS_PRIVATE ?>/wip/getpmts.php",
                type: "post",
                success: function(r) {
                    $('#pmtbody').html('')
                    $('#pmtmodal').modal('hide')

                },
                error: function(xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(xhr.responseText);
                    console.log(thrownError);
                }
            });


        }

        function hideMsg(id) {

            $.ajax({
                data: "id=" + id,
                url: "<?= COMPONENTS_PRIVATE ?>/wip/shopalerts.php",
                type: "post",
                success: function(r) {
                    $('#shopmsg' + id).hide()
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(xhr.responseText);
                    console.log(thrownError);
                }
            })

        }

        function apptResponse(id, email) {
            $('#appointment_id').val(id)
            if (email.length > 0) {
                $('#emailmessageaddress').val(email)
                $('#apptmodal').modal('hide')
                $('#confirmapptmodal').modal('show')
            } else {
                swal("Email address not supplied so you cannot respond to the appointment request")
            }
        }

        function sendEmailMessage() {

            if ($('#emailmodal').css("display") == "none") {
                $('#emailmodal').modal('show')
            } else {
                $('#spinner').show()
                email = $('#emailmessageaddress').val()
                msg = $('#emailmessagemessage').val()
                subj = $('#emailmessagesubject').val()
                if (email.length >= 1 && msg.length > 0 && subj.length > 0) {
                    $.ajax({
                        data: "shopid=<?php echo $shopid; ?>&roid=1234&t=sendemail&email=" + email + "&subj=" + subj + "&msg=" + msg,
                        type: "post",
                        url: "<?= COMPONENTS_PRIVATE ?>/ro/saveData.php",
                        error: function(xhr, ajaxOptions, thrownError) {
                            console.log(xhr.status);
                            console.log(xhr.responseText);
                            console.log(thrownError);
                        },
                        success: function(r) {
                            $('#spinner').hide()
                            var aid = $('#appointment_id').val()
                            $.ajax({
                                data: "t=dismissappt&shopid=<?php echo $shopid; ?>&id=" + aid,
                                url: "<?= COMPONENTS_PRIVATE ?>/wip/getnewappts.php",
                                type: "post",
                                success: function(r) {
                                    if (r == "success") {

                                    }
                                },
                                error: function(xhr, ajaxOptions, thrownError) {
                                    console.log(xhr.status);
                                    console.log(xhr.responseText);
                                    console.log(thrownError);
                                }
                            });

                            if (r == "success") {
                                swal("Email Message Sent")
                                $('#confirmapptmodal').modal('hide')
                            }
                        }
                    });
                } else {
                    swal("You must enter an email, subject and message")
                    $('#spinner').hide()
                }

            }
        }


        function alfredCommands(command) {

            command = command.toLowerCase()

            //console.log(command)
            if (command == "alfred help") {
                $('#alfredmodal').modal('show')
            }
            if (command == "create repair order") {
                top.location.href = "<?= COMPONENTS_PRIVATE ?>/customer/customer-search.php"
            }
            if (command == "whip") {
                top.location.href = "<?= COMPONENTS_PRIVATE ?>/wip/wip.php"
            }
            if (command == "cancel help") {
                $('#alfredmodal').modal('hide')
            }
            if (command == "clear search") {
                wip.contentWindow.document.getElementById("srch").value = ""
                srchtext = ""
                wip.contentWindow.searchTable(srchtext)
                wip.contentWindow.searchClosed(srchtext)
            }
            if (command == "manage customers") {
                top.location.href = "<?= COMPONENTS_PRIVATE ?>/customer/customer-find.php"
            }
            if (command.indexOf("find repair order") >= 0) {
                rar = command.split("order")
                srchtext = rar[1]
                srchtext = srchtext.trim()
                wip = document.getElementById("myframe")
                wip.contentWindow.document.getElementById("srch").value = srchtext
                $('#spinner').show()
                wip.contentWindow.searchTable(srchtext)
                wip.contentWindow.searchClosed(srchtext)
                $('#spinner').hide()
            }
            if (command.indexOf("open repair order") >= 0 || command.indexOf("open ro") >= 0) {
                if (command.indexOf("order") >= 0) {
                    rar = command.split("order")
                } else if (command.indexOf("ro") >= 0) {
                    rar = command.split("ro")
                }
                srchtext = rar[1]
                srchtext = srchtext.trim()
                srchtext = srchtext.replace(":", "")
                // check to see if it is a valid ro
                $.ajax({
                    data: "t=findro&shopid=<?php echo $shopid; ?>&roid=" + srchtext,
                    url: "<?= COMPONENTS_PRIVATE ?>/wip/alfred.php",
                    type: "post",
                    success: function(r) {
                        if (r == "found") {
                            top.location.href = "<?= COMPONENTS_PRIVATE ?>/ro/ro.php?roid=" + srchtext
                        } else {
                            swal("RO " + srchtext + " was not found")
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        console.log(xhr.status);
                        console.log(xhr.responseText);
                        console.log(thrownError);
                    }
                });
            }

        }



        function saveReminder() {

            r = encodeURIComponent($('#newreminder').val())
            ts = encodeURIComponent($('#newreminderdate').val())
            if (r.length > 0 && ts.length > 0) {
                $.ajax({
                    data: "t=addreminder&shopid=<?php echo $shopid; ?>&r=" + r + "&empid=<?php echo $empid; ?>&ts=" + ts,
                    url: "<?= COMPONENTS_PRIVATE ?>/wip/checkreminders.php",
                    type: "post",
                    success: function(r) {
                        //console.log(r)
                        if (r == "success") {
                            changeIconColor()
                        }
                        $('#addremindermodal').modal('hide')
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        console.log(xhr.status);
                        console.log(xhr.responseText);
                        console.log(thrownError);
                    }
                });
            } else {
                swal("You must enter something in the Reminder box and select a date and time")
            }
        }

        var remrotate

        function changeIconColor() {
            $('#reminderbutton').css("color", "white")
            $.ajax({
                data: "t=getreminders&empid=<?php echo $empid; ?>&shopid=<?php echo $shopid; ?>",
                url: "<?= COMPONENTS_PRIVATE ?>/wip/checkreminders.php",
                type: "post",
                success: function(r) {
                    clearInterval(remrotate)
                    if (r == "none" || r == "") {
                        $('#reminderlist').html("<h3>You have no Personal Reminders on your list.  Click below to add one</h3>")
                        $('#remindermodal').modal('show')
                        //
                    } else {
                        $('#reminderlist').html(r)
                        $('#remindermodal').modal('show')
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(xhr.responseText);
                    console.log(thrownError);
                }
            })
        }

        function markDone(id) {

            swal({
                    title: "Are you sure?",
                    text: "You cannot recover this.  You will need to recreate it. Are you sure you want to mark it Done?",
                    type: "warning",
                    showCancelButton: true,
                    confirmButtonClass: "btn-danger",
                    confirmButtonText: "Mark it Complete",
                    closeOnConfirm: true
                },
                function() {
                    $.ajax({
                        data: "t=markdone&shopid=<?php echo $shopid; ?>&id=" + id,
                        url: "<?= COMPONENTS_PRIVATE ?>/wip/checkreminders.php",
                        type: "post",
                        success: function(r) {
                            $.ajax({
                                data: "t=getreminders&empid=<?php echo $empid; ?>&shopid=<?php echo $shopid; ?>",
                                url: "<?= COMPONENTS_PRIVATE ?>/wip/checkreminders.php",
                                type: "post",
                                success: function(r) {
                                    //console.log(r)
                                    if (r == "none") {
                                        $('#reminderlist').html("<h3>You have no Personal Reminders on your list.  Click below to add one</h3>")
                                        $('#reminderbutton').css("color", "white")
                                    } else {
                                        $('#reminderlist').html(r)
                                    }
                                },
                                error: function(xhr, ajaxOptions, thrownError) {
                                    console.log(xhr.status);
                                    console.log(xhr.responseText);
                                    console.log(thrownError);
                                }
                            })

                        },
                        error: function(xhr, ajaxOptions, thrownError) {
                            console.log(xhr.status);
                            console.log(xhr.responseText);
                            console.log(thrownError);
                        }
                    })
                });

        }

        function confirmTechMode() {
            swal({
                    title: "Log Into Tech Mode",
                    text: "This will log you into Tech Mode.  Are you sure?",
                    type: "warning",
                    showCancelButton: true,
                    confirmButtonClass: "btn-danger",
                    confirmButtonText: "Take me to Tech Mode",
                    closeOnConfirm: true
                },
                function() {
                    var uri = '<?= $_SERVER['SERVER_NAME'] ?>';
                    top.location.href = 'https://' + (uri.indexOf("staging") > -1 ? 'staging.' : (uri.indexOf("alpha") > -1 ? 'alpha.' :'')) + 'tech.' + (uri.indexOf("matco") > -1 ? 'matcosms' : (uri.indexOf("protractorgo") > -1?'protractorgo':'shopbosspro')) + '.com/wip.asp?empid=<?php echo $_COOKIE['empid']; ?>&shopname=<?php echo urlencode($_COOKIE['shopname']); ?>&shopid=<?php echo urlencode($_COOKIE['shopid']); ?>&login=<?php echo urlencode($_COOKIE['username']); ?>&mode=<?php echo urlencode($_COOKIE['mode']); ?>'

                });
        }

        var imrotate = ""

        function showIM() {

            clearInterval(imrotate)
            imrotate = ""
            $("#imbutton").css("color", "white").html('<i class="fa fa-commenting"></i>')
            $('#alertmessagediv').hide()
            $.ajax({
                data: "t=getmsg&shopid=<?php echo $shopid; ?>&usr=<?php echo addslashes($usr); ?>",
                url: "<?= COMPONENTS_PRIVATE ?>/wip/chatinfo.php",
                type: "post",
                success: function(r) {
                    $('#imlist').html(r)
                    $('#immodal').modal('show')
                    setTimeout(function() {
                        $("#imlist").scrollTop($("#imlist")[0].scrollHeight)
                    }, 200)
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(xhr.responseText);
                    console.log(thrownError);
                }
            });
        }

        function sendMessage() {
            shopid = "<?php echo $shopid; ?>";
            mto = $('#emps').val();
            msg = encodeURIComponent($('#msgboxsend').val())
            usr = encodeURIComponent("<?php echo addslashes($usr); ?>");
            ds = "t=sendmsg&shopid=" + shopid + "&usr=" + usr + "&to=" + mto + "&msg=" + msg
            if (mto != "none" && msg != '') {
                $.ajax({
                    data: ds,
                    url: "<?= COMPONENTS_PRIVATE ?>/wip/chatinfo.php",
                    type: "post",
                    success: function(r) {
                        $.ajax({
                            data: "t=getmsg&shopid=<?php echo $shopid; ?>&usr=" + usr,
                            url: "<?= COMPONENTS_PRIVATE ?>/wip/chatinfo.php",
                            type: "post",
                            success: function(r) {
                                $('#imlist').html(r)
                                $('#msgboxsend').val('')
                                $('#emps').val('none')
                                setTimeout(function() {
                                    $("#imlist").scrollTop($("#imlist")[0].scrollHeight)
                                }, 200)
                            },
                            error: function(xhr, ajaxOptions, thrownError) {
                                console.log(xhr.status);
                                console.log(xhr.responseText);
                                console.log(thrownError);
                            }
                        });
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        console.log(xhr.status);
                        console.log(xhr.responseText);
                        console.log(thrownError);
                    }
                });
                //msgbutton = document.getElementById("msgbutton")
                //msgbutton.scrollIntoView(false)
            } else {
                swal("You must type a message AND select someone to send the message to")
            }
        }

        function alertActions(p, t) {

            eModal.iframe({
                title: t,
                url: p,
                size: eModal.size.xl,
                buttons: [{
                    text: 'Close',
                    style: 'warning',
                    close: true
                }]
            });


        }

        function extendedKPIs() {

            $('#techaction').css("z-index", "999");
            eModal.iframe({
                title: "Extented KPI's",
                url: "<?= COMPONENTS_PRIVATE ?>/reports/goals.php?m=<?php echo date("Y-m-d", strtotime("first day of this month")); ?>",
                size: eModal.size.xl,
                buttons: [{
                    text: 'Close',
                    style: 'warning',
                    close: true,
                    click: setTechAction
                }]
            });

        }

        function extendedPPH() {
            $('#techaction').css("z-index", "999");
            eModal.iframe({
                title: "PPH Net Variance",
                url: "<?= COMPONENTS_PRIVATE ?>/settings/pb_variance.php",
                size: eModal.size.xl,
                buttons: [{
                    text: 'Close',
                    style: 'warning',
                    close: true,
                    click: setTechAction
                }]
            })
        }

        function setTechAction() {
            $('#techaction').css("z-index", "9999");
        }


        function maxTA() {

            $('#techaction').animate({
                height: 400,
                width: 800
            })
            $('#techheader').animate({
                width: 800
            })

        }

        function minTA() {

            $('#techaction').animate({
                height: 100,
                width: 400
            })
            $('#techheader').animate({
                width: 400
            })

        }

        function showShopStats() {

            if ($('#shopstats').html() == "") {
                $('#spinner').show().css("left", "50px");
            } else {
                $('#spinner').hide().css("left", "49%");
            }
            $('#shopstats').toggle()

            ssclock = setInterval(function() {
                if ($('#shopstats').html() == "") {
                    $('#spinner').show().css("left", "50px");
                } else {
                    $('#spinner').hide().css("left", "49%");
                    clearInterval(ssclock)
                }
            }, 500)

        }

        function openTA() {

            //console.log("openta")
            $("#techaction").slideToggle('fast')
            $('#techactivities').fadeOut('fast')
            $('#turnoffta').fadeOut('fast')
        }

        function closeTA() {

            //$("#techaction").effect("transfer",{to:"#techactivities",className:"ui-effects-transfer"},500);
            $("#techaction").slideToggle('slow')
            $('#techactivities').fadeIn()
            $('#turnoffta').fadeIn()
        }

        $('.shopnotice').mousedown(function(e) {
            //console.log("dragging")
            drag = $(this).closest('.draggable')
            drag.addClass('dragging')
            $(this).on('mousemove', function(e) {
                drag.css('left', e.clientX - $(this).width() / 2)
                drag.css('top', e.clientY - $(this).height() / 2 - 10)
                window.getSelection().removeAllRanges()
            })
        })
        $('.shopnotice').mouseleave(stopDragging)
        $('.shopnotice').mouseup(stopDragging)
        $('.shopnotice').blur(function() {
            msg = encodeURIComponent($(this).html())
            $.ajax({
                data: "shopid=<?php echo $shopid; ?>&msg=" + msg,
                url: "<?= COMPONENTS_PRIVATE ?>/wip/shopnotice.php",
                success: function(r) {
                    //console.log(r)
                }
            });
        });


        function checkPassChange() {
            $.post("<?= COMPONENTS_PUBLIC ?>/login/loginaction.php", {
                t: 'checkpasschange',
                empid: '<?= $empid ?>',
                shopid: '<?= $shopid ?>'
            }, function(data) {
                if (data == 'yes') {
                    $("#changepassmodal").modal({
                        backdrop: 'static',
                        keyboard: false
                    });
                }
            });
        }

        function changepass() {
            $('#changepassmsg').html("")
            var newpass = $('#newpass').val()
            var cnewpass = $('#cnewpass').val()
            var number = /([0-9])/;
            var alphabets = /([a-zA-Z])/;
               
            if (newpass == '' || cnewpass == '') {
                $('#changepassmsg').html("<span style='color:red'>*Passwords cannot be blank</span>")
                return
            } else if (newpass != cnewpass) {
                $('#changepassmsg').html("<span style='color:red'>*Passwords do not match</span>")
                return
            }
            else if(newpass.length < 8 || !newpass.match(number) || !newpass.match(alphabets))
            {
                $('#changepassmsg').html("<span style='color:red'>*Password should be minimum eight characters long, at least one letter, at least one number.</span>")
                return
            }

            $('#btn-change-pass').attr('disabled', 'disabled')

            $.post("<?= COMPONENTS_PUBLIC ?>/login/loginaction.php", {
                t: 'changepass',
                empid: '<?= $empid ?>',
                shopid: '<?= $shopid ?>',
                password: newpass
            }, function(data) {
                if (data.status == 'success') {
                    $('#changepassmsg').html("<span style='color:green'>*Password changed successfully. Please login using the new password.</span>")
                    setTimeout(function() {
                        document.location = '<?= COMPONENTS_PRIVATE ?>/login/logoff.php'
                    }, 2000);
                } else {
                    $('#changepassmsg').html("<span style='color:red'>*" + data.msg + "</span>")
                    $('#btn-change-pass').attr('disabled', false)
                }

            }, 'json');

        }

        var timer;
        var timer2;

        function checkSMS() {

            timer2 = setInterval(function() {
                if ($('#myframe').attr("src") != "smslive.php") {
                    $.ajax({
                        data: "t=getcount&shopid=<?php echo $shopid; ?>",
                        url: "<?= COMPONENTS_PRIVATE ?>/shared/smsliveaction.php",
                        type: "post",
                        error: function(xhr, ajaxOptions, thrownError) {
                            console.log(xhr.status);
                            console.log(xhr.responseText);
                            console.log(thrownError);
                        },
                        success: function(r) {
                            //console.log(r)
                            if (r > 0) {
                                if ($('#smstab').hasClass("btn-danger")) {
                                    $('#smstab').removeClass("btn-danger").addClass("btn-success").html("<img style='width:20px;height:20px;' src='<?= IMAGE ?>/loaderbig.gif'> YOU HAVE NEW LIVE TEXT MESSAGES!")
                                } else {
                                    $('#smstab').removeClass("btn-success").addClass("btn-danger").html("<img style='width:20px;height:20px;' src='<?= IMAGE ?>/loaderbig.gif'> YOU HAVE NEW LIVE TEXT MESSAGES!")
                                }
                            } else {
                                $('#smstab').removeClass("btn-success").addClass("btn-danger").removeClass("active").html("LIVE TEXT")
                            }
                        }
                    });
                }
                <?php
                if ($autoshowta == "yes") {
                ?>
                    $.ajax({
                        data: "shopid=<?php echo $shopid; ?>",
                        url: "<?= COMPONENTS_PRIVATE ?>/wip/techactivities.php",
                        type: "post",
                        error: function(xhr, ajaxOptions, thrownError) {
                            //console.log(xhr.status);
                            //console.log(xhr.responseText);
                            //console.log(thrownError);
                        },
                        success: function(r) {
                            $('#techresults').html(r)
                            //console.log("ta updated")
                        }
                    });
                <?php
                }
                ?>

                // add the check for IM
                $.ajax({
                    data: "t=countmsg&shopid=<?php echo $shopid; ?>&usr=<?php echo addslashes($usr); ?>",
                    url: "<?= COMPONENTS_PRIVATE ?>/wip/chatinfo.php",
                    type: "post",
                    success: function(r) {

                        if (r > 0) {
                            state = true
                            $('#imbutton').html('<i class="fa fa-commenting"></i>' + r)
                            $('#alertmessagediv').show()

                            if (imrotate == "") {
                                imrotate = setInterval(function() {
                                    if (state) {
                                        $("#imbutton").animate({
                                            color: "red",
                                        }, 1000);

                                    } else {
                                        $("#imbutton").animate({
                                            color: "white"
                                        }, 1000);

                                    }
                                    state = !state;
                                }, 1000);
                            }
                        } else {
                            $("#imbutton").css("color", "white")
                            if (imrotate != "") {
                                clearInterval(imrotate)
                                imrotate = ""
                            }
                            $('#alertmessagediv').hide()
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        console.log(xhr.status);
                        console.log(xhr.responseText);
                        console.log(thrownError);
                    }
                });

            }, 60000)

        }

        function changeWip(tab) {
            var iOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            var actived_nav = $('.nav-tabs > li.active');
            actived_nav.removeClass('active');
            $('#' + tab).addClass("active")
            Cookies.set('tab', tab, {
                expires: 360
            });
            if (tab == "wipbystatustab") {
                $('#spinner').show()
                $('#smstab').removeClass("btn-success").addClass("btn-danger").removeClass("active").html("LIVE TEXT")
                checkSMS()
                $('#myframe').hide().attr("src", "<?= COMPONENTS_PRIVATE ?>/wip/wiplist.php?o=status&shopid=<?php echo $shopid; ?>").height(h).show()
                $('#spinner').hide()
            } else if (tab == "wipbytypetab") {
                $('#spinner').show()
                $('#smstab').removeClass("btn-success").addClass("btn-danger").removeClass("active").html("LIVE TEXT")
                checkSMS()
                $('#myframe').hide().attr("src", "<?= COMPONENTS_PRIVATE ?>/wip/wiplist.php?o=type&shopid=<?php echo $shopid; ?>").height(h).show()
                $('#spinner').hide()
            } else if (tab == "timeclocktab") {
                $('#spinner').show()
                $('#smstab').removeClass("btn-success").addClass("btn-danger").removeClass("active").html("LIVE TEXT")
                checkSMS()
                $('#myframe').hide().attr("src", "<?= COMPONENTS_PRIVATE ?>/wip/timeclock.php?shopid=<?php echo $shopid; ?>").height(h).show()
                $('#spinner').hide()
            } else if (tab == "wipbyrotab") {
                $('#spinner').show()
                $('#smstab').removeClass("btn-success").addClass("btn-danger").removeClass("active").html("LIVE TEXT")
                checkSMS()
                $('#myframe').hide().attr("src", "<?= COMPONENTS_PRIVATE ?>/wip/wiplist.php?o=ro&shopid=<?php echo $shopid; ?>").height(h).show()
                $('#spinner').hide()
            } else if (tab == "wipbydatetab") {
                $('#spinner').show()
                $('#smstab').removeClass("btn-success").addClass("btn-danger").removeClass("active").html("LIVE TEXT")
                checkSMS()
                $('#myframe').hide().attr("src", "<?= COMPONENTS_PRIVATE ?>/wip/wiplist.php?o=date&shopid=<?php echo $shopid; ?>").height(h).show()
                $('#spinner').hide()
            } else if (tab == "motortab") {
                $('#spinner').show()
                $('#smstab').removeClass("btn-success").addClass("btn-danger").removeClass("active").html("LIVE TEXT")
                checkSMS()
                $('#myframe').hide().attr("src", "<?= COMPONENTS_PRIVATE ?>/wip/loadmotor.php?ios=" + iOS + "&shopid=<?php echo $shopid; ?>&pid=<?php echo $pid; ?>").height(h).show()
                $('#spinner').hide()
            } else if (tab == "smstab") {
                $('#smstab').removeClass("btn-success").addClass("btn-danger").removeClass("active").html("LIVE TEXT")
                $('#spinner').show()
                $('#myframe').hide().attr("src", "<?= COMPONENTS_PRIVATE ?>/shared/smslive.php").height(h).show()
                $('#spinner').hide()
            } else if (tab == "kanban") {
                //$('#smstab').removeClass("btn-success").addClass("btn-danger").removeClass("active").html("LIVE TEXT")
                //$('#spinner').show()
                $('#myframe').hide().attr("src", "<?= COMPONENTS_PRIVATE ?>/workflow/board.php").height(h).show()
                $('#spinner').hide()
            }
            else{
                tab = tab.replace(/_/g," ")
                $('#spinner').show()
                $('#myframe').hide().attr("src","<?= COMPONENTS_PRIVATE ?>/wip/wiplist.php?o=ro&shopid=<?php echo $shopid; ?>&w="+tab).height(h).show()
                $('#spinner').hide()
            }


            $('#myframe').focus()

        }

        function stopDragging() {
            drag = $(this).closest('.draggable')
            drag.removeClass('dragging')
            $(this).off('mousemove')
        }

        function loadWip() {
            //$('#spinner').show()
            r = Math.random()
            h = $(document).height() - 110 + "px"
            var actived_nav = $('.nav-tabs > li.active')
            tab = $(actived_nav).attr("id")
            //console.log("framesrc: "+$('#myframe').attr("src"))
            kanban = Cookies.get("kanban")
            if (kanban == "yes") {
                $('#myframe').hide().attr("src", "<?= COMPONENTS_PRIVATE ?>/wip/wiplist-kanban.php").height(h).show()
            } else {
                $('#myframe').hide().attr("src", "<?= COMPONENTS_PRIVATE ?>/wip/wiplist.php?r=" + r + "&o=status&shopid=<?php echo $shopid; ?>").height(h).show()
            }
        }

        function loadWipByButton() {

            Cookies.remove('wipsort')
            Cookies.remove('kanban')
            Cookies.set('tab', 'wip', {
                expires: 360
            });
            var actived_nav = $('.nav-tabs > li.active');
            actived_nav.removeClass('active');
            r = Math.random()
            h = $(document).height() - 110 + "px"
            $('#myframe').hide().attr("src", "<?= COMPONENTS_PRIVATE ?>/wip/wiplist.php?r=" + r + "&o=status&shopid=<?php echo $shopid; ?>").height(h).show()
            /*$('#wipbystatustab').addClass("active")
            $('#wipbytypetab').removeClass()
            $('#wipbyrotab').removeClass()
            $('#wipbydatetab').removeClass()
            $('#timeclocktab').removeClass()*/
            $('#smstab').removeClass("btn-success").removeClass("btn-warning").removeClass("active").addClass("btn-danger")
            clearInterval(timer2)
            checkSMS()
        }


        function installAndroid() {
            location.href = 'https://play.google.com/store/apps/details?id=com.shopbosspro.vinscanner&amp;hl=enhttps://play.google.com/store/apps/details?id=com.shopbosspro.vinscanner&amp;hl=en'
        }

        function installApple() {
            location.href = 'https://itunes.apple.com/us/app/sbpvinscanner/id1120010438?mt=8'
        }

        function launchScanner() {

            location.href = 'sbpvinscan://?returnURL=https://<?php echo $_SERVER['SERVER_NAME']; ?>/sbp/api/vinscan/?roid=XXXX,shopid=<?php echo $shopid; ?>'

        }

        function loadNewMotor() {
            $('#motorframe').show().attr("src", "<?= COMPONENTS_PRIVATE ?>/ro/motorfiles/loadnewmotor.php?shopid=<?php echo $shopid; ?>")
        }

        function closeMotor() {
            $('#motorframe').attr("src", "").hide()
        }


        $(document).ready(function() {


            $('#base-material-text').keyup(function() {
                searchTable($(this).val());
            });


            $(window).resize(function() {
                currwidth = $(this).width()
                console.log(currwidth)
                if (currwidth <= 685) {
                    //closeTA()
                }
                h = $(document).height() - 110 + "px"
                $('#myframe').height(h)
            });

            // check for the tab cookie and if it exists, set the focus on that tab
            wipsort = Cookies.get('wipsort');
            //console.log(wipsort)

            <?php
            if (strlen($msgids) > 0) {
                echo "$('#shopalertsmodal').modal('show');\r\n";
            }
            ?>

            loadWip()

            // get shop stats
            setTimeout(function() {
                $.ajax({
                    data: "shopid<?php echo $shopid; ?>",
                    url: "<?= COMPONENTS_PRIVATE ?>/wip/wipstats.php",
                    success: function(r) {
                        //console.log(r)
                        $('#shopstats').append(r)
                    }
                });
            }, 5000);
            $('#base-material-text').focus()

            wtab = Cookies.get('tab')
            if (wtab != 'undefined' && wtab!=null && wtab != "timeclocktab" && wtab != "motortab" && wtab != "smstab" && wtab != "wip") {
                changeWip(wtab)
            }

            // check for cores once
            setTimeout(function() {
                $.ajax({
                    data: "",
                    url: "<?= COMPONENTS_PRIVATE ?>/cores/corecheck.php",
                    success: function(r) {
                        //console.log(r)
                        if (r > 0) {
                            $('#cores').css("background-color", "red").css("font-weight", "bold").css("text-transform", "uppercase")
                        } else {
                            $('#cores').css("background-color", "transparent").css("font-weight", "normal").css("text-transform", "capitalize")
                        }
                    }
                });
            }, 2500);

            // check for cores every 60 seconds
            setInterval(function() {
                $.ajax({
                    data: "",
                    url: "<?= COMPONENTS_PRIVATE ?>/cores/corecheck.php",
                    success: function(r) {
                        //console.log(r)
                        if (r > 0) {
                            $('#cores').css("background-color", "red").css("font-weight", "bold").css("text-transform", "uppercase")
                        } else {
                            $('#cores').css("background-color", "transparent").css("font-weight", "normal").css("text-transform", "capitalize")
                        }
                    }
                });
            }, 60000);

            setInterval(function() {
                s = $('#myframe').attr("src")
                if (s.indexOf("wiplist.php") > 0) {
                    //console.log("wip reload")
                    loadWip()
                } else {
                    //console.log("no reload")
                }
            }, 300000);


            // check for new appointments
            setTimeout(function() {
                $.ajax({
                    data: "t=getappts&shopid=<?php echo $shopid; ?>",
                    url: "<?= COMPONENTS_PRIVATE ?>/wip/getnewappts.php",
                    type: "post",
                    success: function(r) {
                        //console.log("schedule check:"+r)
                        if (r != '' && !$('#changepassmodal').is(':visible')) {
                            $('#apptbody').append(r)
                            $('#apptmodal').modal('show')
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        console.log(xhr.status);
                        console.log(xhr.responseText);
                        console.log(thrownError);
                    }
                });
            }, 5000)

            // check for new appointments
            setTimeout(function() {
                $.ajax({
                    data: "t=getpmts&shopid=<?php echo $shopid; ?>",
                    url: "<?= COMPONENTS_PRIVATE ?>/wip/getpmts.php",
                    type: "post",
                    success: function(r) {
                        //console.log("remote pay"+r)
                        if (r != '' && !$('#changepassmodal').is(':visible')) {
                            $('#pmtbody').append(r)
                            $('#pmtmodal').modal('show')
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        console.log(xhr.status);
                        console.log(xhr.responseText);
                        console.log(thrownError);
                    }
                });
            }, 5000)

            setInterval(function() {

                $.ajax({
                    data: "t=getreminders&empid=<?php echo $empid; ?>&shopid=<?php echo $shopid; ?>",
                    url: "<?= COMPONENTS_PRIVATE ?>/wip/checkreminders.php",
                    type: "post",
                    success: function(r) {
                        //console.log(r)
                        if (r == "none" || r == "") {
                            $('#reminderbutton').css("color", "white")
                            $('#reminderlist').html("<h3>You have no Personal Reminders on your list.  Click below to add one</h3>")
                        } else {
                            issuccess = r.indexOf("alert-success")
                            isoverdue = r.indexOf("alert-danger")
                            if (issuccess > 0) {
                                remstate = true
                                remrotate = setInterval(function() {
                                    //console.log(remstate)
                                    if (remstate) {
                                        $("#reminderbutton").css("color", "orange")
                                        //console.log($("#reminderbutton").css("color"))
                                    } else {
                                        $("#reminderbutton").css("color", "white")
                                        //console.log($("#reminderbutton").css("color"))
                                    }
                                    remstate = !remstate;
                                }, 1000);
                                $('#reminderlist').html(r)
                                $('#remindermodal').modal('show')
                            } else if (isoverdue > 0) {
                                $('#reminderbutton').css("color", "orange")
                                $('#reminderlist').html(r)
                            } else {
                                $('#reminderlist').html(r)
                            }
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        console.log(xhr.status);
                        console.log(xhr.responseText);
                        console.log(thrownError);
                    }
                })

                $.ajax({
                    data: "showall=no",
                    url: "<?= INTEGRATIONS ?>/lyft/ridestatuscount.php",
                    type: "get",
                    success: function(r) {
                        r = parseFloat(r)
                        //console.log("lyftcount:"+r)
                        if (r > 0) {
                            eModal.iframe({
                                title: "Lyft Activity",
                                url: "<?= INTEGRATIONS ?>/lyft/ridestatus.php",
                                size: eModal.size.xl,
                                buttons: [{
                                    text: 'Close',
                                    style: 'warning',
                                    close: true
                                }]
                            });
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        console.log(xhr.status);
                        console.log(xhr.responseText);
                        console.log(thrownError);
                    }
                });

            }, 60000);


            $('#trialclose').on('click', function() {
                $.post("<?= COMPONENTS_PRIVATE ?>/wip/setcookie.php", function(data) {
                    location.reload();
                });

            })


            setInterval(function() {
                get_notifications();
            }, 60000);
            setTimeout(function() {
                $.post("notification_actions.php", {
                    type: 'activecount',
                    shopid: '<?= $shopid ?>'
                }, function(data) {
                    if (data > 0)
                        $('#notification_badge').html(data);
                });

            }, 6000);


            $('#notificationmodal').on('click', '.btn-dismiss', function() {
                var $this = $(this);
                var did = $this.data('id');
                $.post("<?= COMPONENTS_PRIVATE ?>/wip/notification_actions.php", {
                    type: 'dismiss',
                    shopid: '<?= $shopid ?>',
                    id: did
                }, function(data) {
                    $this.parent().remove();
                    if ($('#notificationmodal .btn-dismiss').length < 1) {
                        $('#notificationmodal').modal('hide');
                        $('#notification_badge').html('');
                    } else
                        $('#notification_badge').html($('#notificationmodal .btn-dismiss').length);
                });

            })

            $('#notificationmodal').on('click', '.btn-dismiss-all', function() {
                swal({
                        title: "Are you sure?",
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonClass: "btn-success",
                        confirmButtonText: "Yes",
                        closeOnConfirm: true
                    },
                    function() {

                        var dismiss_ids = [];
                        $.each($('#notificationmodal .btn-dismiss'), function() {
                            dismiss_ids.push($(this).data('id'));
                        });

                        var ids = dismiss_ids.join(",");

                        $.post("<?= COMPONENTS_PRIVATE ?>/wip/notification_actions.php", {
                            type: 'dismissall',
                            shopid: '<?= $shopid ?>',
                            ids: ids
                        }, function(data) {
                            $('#notificationmodal').modal('hide');
                            $('#notification_badge').html('');
                        });


                    });

            })

            $('#ocrimage').on('change', function() {
                if ($(this).val() != '') {
                    $('#pictureslot').html("<img src='" + URL.createObjectURL(event.target.files[0]) + "' width='200' height='200' border='1'>").show();
                }

            })

            $('#btn-ocrscan').on('click', function() {
                if ($('#ocrimage').val() == '') {
                    swal("Please select VIN image to upload")
                    return
                }

                var $this = $(this);
                $this.attr('disabled', 'disabled');
                $this.html('Please Wait...');

                formData = new FormData(document.forms.namedItem("ocrform"));
                $.ajax({
                    url: '<?= COMPONENTS_PRIVATE ?>/vinscanner/html/dist/ocrscan.php?savevin=yes',
                    type: 'POST',
                    data: formData,
                    cache: false,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(data) {

                        $('#bossvinscanmodal').modal('hide');

                        if (data.status == 'success') {
                            $('#scanmodal').modal('show')
                            getScannedVins()
                        } else
                            swal(data.status != '' ? data.status : "No Results Found")

                        $('#ocrimage').val('');
                        $('#pictureslot').html('').hide();
                        $this.attr('disabled', false);
                        $this.html('Scan');
                    }
                });

            });

            <?php if($showmatcovideo=='yes'){?>
             openQuickStartVideo("<a data-embed='https://www.youtube.com/embed/1qy5OtK1UOM'></a>")
            <?php }?>



        });


        function get_notifications(t = '') {
            $.post("<?= COMPONENTS_PRIVATE ?>/wip/notification_actions.php", {
                type: 'getlist',
                shopid: '<?= $shopid ?>',
                trigger: t
            }, function(data) {
                if (data.content != '' || t == 'panel') {
                    if (data.content == '')
                    data = "<h3>Good Job!</h3><br><p>You have cleared all of your shop notifications.</p>";
                    else if(data.content!=storednotifications && data.chimes!='')
                    {
                      var chimes = data.chimes.split(',')
                      for(var i = 0; i < chimes.length; i++)
                      {
                        ion.sound.play(chimes[i]);
                      }
                    }
                    storednotifications = data.content
                    $('#notificationmodal .block-content #notificationlist').html(data.content);
                    $('#notificationmodal').modal('show');
                    if ($('#notificationmodal .btn-dismiss').length > 0)
                    $('#notification_badge').html($('#notificationmodal .btn-dismiss').length);
                }
            },'json');
        }

        function gotoro(roid) {

            location.href = "<?= COMPONENTS_PRIVATE ?>/ro/ro.php?showpmts=yes&roid=" + roid

        }

        function dismissAllAppt() {
            swal({
                    title: "Are you sure?",
                    text: "Are you sure you want to mark all of these appointments as viewed?",
                    type: "warning",
                    showCancelButton: true,
                    confirmButtonClass: "btn-danger",
                    confirmButtonText: "Yes, Mark Them!",
                    closeOnConfirm: false
                },
                function() {
                    $.ajax({
                        data: "t=dismissall&shopid=<?php echo $shopid; ?>",
                        url: "<?= COMPONENTS_PRIVATE ?>/wip/getnewappts.php",
                        type: "post",
                        success: function(r) {
                            if (r == "success") {
                                swal("All appointments marked as read")
                                $('#apptmodal').modal('hide')
                                $('#apptbody').html('')
                            }
                        },
                        error: function(xhr, ajaxOptions, thrownError) {
                            console.log(xhr.status);
                            console.log(xhr.responseText);
                            console.log(thrownError);
                        }
                    });

                });
        }

        function dismissAppt(id, email) {

            $.ajax({
                data: "t=dismissappt&shopid=<?php echo $shopid; ?>&id=" + id,
                url: "<?= COMPONENTS_PRIVATE ?>/wip/getnewappts.php",
                type: "post",
                success: function(r) {
                    if (r == "success") {
                        $('#apptmodal').modal('hide')
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(xhr.responseText);
                    console.log(thrownError);
                }
            });

        }

        function gotoSchedule(id, d) {
            //console.log(d+":"+id)
            // mark it viewed
            /*$.ajax({
                data: "t=dismissappt&shopid=<?php echo $shopid; ?>&id="+id,
                        url: "php/getnewappts.php",
                        type: "post",
                        success: function(r){
                            if (r == "success"){*/
            location.href = '<?= COMPONENTS_PRIVATE ?>/calendar/calendar.php?d=' + d
            /*      }
                 },
                 error: function (xhr, ajaxOptions, thrownError) {
                     console.log(xhr.status);
                     console.log(xhr.responseText);
                     console.log(thrownError);
                 }
             });*/
        }

        function getScannedVins() {
            $('#spinner').show()
            $.ajax({
                url: "<?= COMPONENTS_PRIVATE ?>/customer/retrievescannedvin.php?s=wip",
                success: function(r) {
                    $('#scannedvins').html(r)
                    $('#spinner').hide()
                }
            });

        }

        function removeVIN(vin) {

            swal({
                    title: "Are you sure?",
                    text: "VIN will be removed",
                    type: "warning",
                    showCancelButton: true,
                    cancelButtonClass: "btn-default",
                    confirmButtonClass: "btn-danger",
                    confirmButtonText: "Remove VIN",
                    closeOnConfirm: true,
                    closeOnCancel: true
                },
                function(isConfirm) {
                    if (isConfirm) {
                        $.ajax({
                            data: "vin=" + vin + "&shopid=<?php echo $shopid; ?>",
                            url: "<?= COMPONENTS_PRIVATE ?>/customer/removevin.php",
                            error: function(xhr, ajaxOptions, thrownError) {
                                //console.log(xhr.status);
                                //console.log(xhr.responseText);
                                //console.log(thrownError);
                            },
                            success: function(r) {
                                //console.log(r)
                                getScannedVins()
                            }
                        });

                    }
                });


        }


        function createROVINScan(customerid, shopid, vehid) {

            swal({
                    title: "Are you sure?",
                    text: "VIN will be removed",
                    type: "warning",
                    showCancelButton: true,
                    cancelButtonClass: "btn-default",
                    confirmButtonClass: "btn-success",
                    confirmButtonText: "Create RO",
                    closeOnConfirm: true,
                    closeOnCancel: true
                },
                function(isConfirm) {
                    if (isConfirm) {
                        location.href = '<?= COMPONENTS_PRIVATE ?>/createro/addconcerns.php?cid=' + customerid + '&vid=' + vehid
                    }
                });
        }

        function launchBossVin() {

            $('#scanmodal').modal('hide')
            $('#bossvinscanmodal').modal('show')

        }

        function resetTop() {
            $('#main-container').css('margin-top', '0')
        }

        function openQuickStartVideo(elem){
            var video = $(elem).data("embed");
            $("#youtubeFrame").attr("src",video);
            $("#quickStartModal").modal('show');

            $("#quickStartModal").on('hide.bs.modal', function (event) {
                $("#youtubeFrame").attr("src",'');
            });
        }

        function hideCapital() {
        $('#main-container').css('margin-top', '0')
        $.ajax({url: "<?= COMPONENTS_PRIVATE ?>/accounting/hidecapital.php"});
        }

        Canny('initChangelog', {
            appID: '61a9055f38287d52f4e1e4cf',
            ssoToken: '<?= $ssoToken ?>',
            position: 'bottom',
            align: 'right',
        });

        ion.sound({
        sounds: [
            {name: "beer_can"},
            {name: "bell_ring"},
            {name: "branch_break"},
            {name: "button_click"},
            {name: "keyboard_click"},
            {name: "big_button"},
            {name: "tiny_button"},
            {name: "camera_flashing"},
            {name: "camera_flashing_2"},
            {name: "cd_tray"},
            {name: "computer_error"},
            {name: "door_bell"},
            {name: "door_bump"},
            {name: "glass"},
            {name: "keyboard_desk"},
            {name: "light_bulb_breaking"},
            {name: "metal_plate"},
            {name: "metal_plate_2"},
            {name: "pop_cork"},
            {name: "snap"},
            {name: "staple_gun"},
            {name: "tap"},
            {name: "water_droplet_1"},
            {name: "water_droplet_2"}
        ],
        path: "<?= ASSETS?>/sounds/",
        preload: false,
        multiplay: true
    });

        console.log(<?= json_encode($_SERVER['USERNAME']) ?>);
    </script>





    <script defer src="<?= SCRIPT; ?>/sbp-pageresize.js?v=1.1"></script>


    <!-- *************************************************************************************************************************************** -->

    <footer style='color:black;background-color:white;text-align:right'>
        <?php
        $wipetime = microtime(true);

        //echo "WIP Page time: ".round(($wipetime - $wipstime),4)." seconds";
        ?>
    </footer>

    <!-- *************************************************************************************************************************************** -->

</body>
<?php if (isset($conn)) {
    mysqli_close($conn);
} ?>

</html>
