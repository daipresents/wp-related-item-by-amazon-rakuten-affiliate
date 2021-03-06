<?php

/* Copyright 2018 Amazon.com, Inc. or its affiliates. All Rights Reserved. */
/* Licensed under the Apache License, Version 2.0. */

// Put your Secret Key in place of **********
$serviceName="ProductAdvertisingAPI";
$region="us-west-2";
$accessKey=get_site_option('wp_raira_amazon_access_key');
$secretKey=get_site_option('wp_raira_amazon_secret_access_key');
$searchIndex=get_site_option('wp_raira_amazon_search_index');
$accociateTag=get_site_option('wp_raira_amazon_associate_tag');
$imageSize=get_site_option('wp_raira_image_size');
$keywords = "アジャイル";
switch (get_site_option('wp_raira_search_by')){
  case "Category Name":
    $categories = get_the_category();
    foreach($categories as $category) {
      $keywords = $category->cat_name;
      break;
    }
  case "Category Description":
    $categories = get_the_category();
    foreach($categories as $category) {
      $keywords = $category->category_description;
      break;
    }
  case "Tag Name":
    $tags = get_the_tags();
    foreach($tags as $tag) {
      $keywords = $tag->name;
      break;
    }
  case "Tag Description":
    $tags = get_the_tags();
    foreach($tags as $tag) {
      $keywords = $tag->description;
      break;
    }
}

if (empty($keywords) || !get_site_option('wp_raira_is_display')) {
  return;
}

$payload="{"
        ." \"Keywords\": \"$keywords\","
        ." \"Resources\": ["
        ."  \"Images.Primary.$imageSize\","
        ."  \"ItemInfo.Title\""
        ." ],"
        ." \"SearchIndex\": \"$searchIndex\","
        ." \"PartnerTag\": \"$accociateTag\","
        ." \"PartnerType\": \"Associates\","
        ." \"Marketplace\": \"www.amazon.co.jp\""
        ."}";
$host="webservices.amazon.co.jp";
$uriPath="/paapi5/searchitems";
$awsv4 = new AwsV5 ($accessKey, $secretKey);
$awsv4->setRegionName($region);
$awsv4->setServiceName($serviceName);
$awsv4->setPath ($uriPath);
$awsv4->setPayload ($payload);
$awsv4->setRequestMethod ("POST");
$awsv4->addHeader ('content-encoding', 'amz-1.0');
$awsv4->addHeader ('content-type', 'application/json; charset=utf-8');
$awsv4->addHeader ('host', $host);
$awsv4->addHeader ('x-amz-target', 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems');
$headers = $awsv4->getHeaders ();
$headerString = "";
foreach ( $headers as $key => $value ) {
    $headerString .= $key . ': ' . $value . "\r\n";
}
$params = array (
        'http' => array (
            'header' => $headerString,
            'method' => 'POST',
            'content' => $payload
        )
    );
$stream = stream_context_create ( $params );

$fp = @fopen ( 'https://'.$host.$uriPath, 'rb', false, $stream );

if (! $fp) {
    throw new Exception ( "Exception Occured" );
}
$response = @stream_get_contents ( $fp );
if ($response === false) {
    throw new Exception ( "Exception Occured" );
}

$response_json = json_decode($response, true);
if ($response_json['Errors']) {
	# response error
	return;
}

$items = $response_json["SearchResult"]["Items"];
?>

<div id='wp-raira'>
<aside id="wp-raira-items">
<?php echo get_site_option('wp_raira_heading_text') ?>

<?php
  $count = 1;
  foreach ($items as $item) {
?>

<article class="wp-raira" style="width:<?php echo $item["Images"]["Primary"]["Medium"]["Width"] ?>px; height:<?php echo $item["Images"]["Primary"]["Medium"]["Height"] ?>px">
  <div class="wp-raira-thumbnail">
    <a href="<?php echo $item["DetailPageURL"] ?>" title="<?php echo $item["ItemInfo"]["Title"]["DisplayValue"] ?>" target="_blank">
      <img src="<?php echo $item["Images"]["Primary"]["Medium"]["URL"] ?>" alt="<?php echo $items["ItemInfo"]["Title"]["DisplayValue"] ?>" title="<?php echo $attributes["item_name"] ?>" style="height:<?php echo $attributes["image_height"] ?>px; width:<?php echo $attributes["image_width"] ?>px" />
    </a>
  </div><!-- .wp-raira-thumb -->
  <?php if (get_site_option('wp_raira_is_display_item_name')){ ?>
  <div class="wp-raira-content">
    <a href="<?php echo $items["DetailPageURL"] ?>" title="<?php echo $item["ItemInfo"]["Title"]["DisplayValue"] ?>" target="_blank">
      <?php echo $item["ItemInfo"]["Title"]["DisplayValue"] ?>
    </a>
  </div><!-- .wp-raira-content -->
  <?php } ?>
</article><!-- .wp-raira-thumbnail -->

<?php
    if (wp_is_mobile()) {
      if ($count < get_site_option('wp_raira_max_item_number_mobile')) {
        $count++;
      } else {
        break;
      }
    } else {
      if ($count < get_site_option('wp_raira_max_item_number_pc')) {
        $count++;
      } else {
        break;
      }
    } // if
  } //foreach
?>

<br style="clear:both;">
</aside><!-- #wp-raira-items -->
</div><!-- #wp-raira -->


<?php
class AwsV5 {

    private $accessKey = null;
    private $secretKey = null;
    private $path = null;
    private $regionName = null;
    private $serviceName = null;
    private $httpMethodName = null;
    private $queryParametes = array ();
    private $awsHeaders = array ();
    private $payload = "";

    private $HMACAlgorithm = "AWS4-HMAC-SHA256";
    private $aws4Request = "aws4_request";
    private $strSignedHeader = null;
    private $xAmzDate = null;
    private $currentDate = null;

    public function __construct($accessKey, $secretKey) {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->xAmzDate = $this->getTimeStamp ();
        $this->currentDate = $this->getDate ();
    }

    function setPath($path) {
        $this->path = $path;
    }

    function setServiceName($serviceName) {
        $this->serviceName = $serviceName;
    }

    function setRegionName($regionName) {
        $this->regionName = $regionName;
    }

    function setPayload($payload) {
        $this->payload = $payload;
    }

    function setRequestMethod($method) {
        $this->httpMethodName = $method;
    }

    function addHeader($headerName, $headerValue) {
        $this->awsHeaders [$headerName] = $headerValue;
    }

    private function prepareCanonicalRequest() {
        $canonicalURL = "";
        $canonicalURL .= $this->httpMethodName . "\n";
        $canonicalURL .= $this->path . "\n" . "\n";
        $signedHeaders = '';
        foreach ( $this->awsHeaders as $key => $value ) {
            $signedHeaders .= $key . ";";
            $canonicalURL .= $key . ":" . $value . "\n";
        }
        $canonicalURL .= "\n";
        $this->strSignedHeader = substr ( $signedHeaders, 0, - 1 );
        $canonicalURL .= $this->strSignedHeader . "\n";
        $canonicalURL .= $this->generateHex ( $this->payload );
        return $canonicalURL;
    }

    private function prepareStringToSign($canonicalURL) {
        $stringToSign = '';
        $stringToSign .= $this->HMACAlgorithm . "\n";
        $stringToSign .= $this->xAmzDate . "\n";
        $stringToSign .= $this->currentDate . "/" . $this->regionName . "/" . $this->serviceName . "/" . $this->aws4Request . "\n";
        $stringToSign .= $this->generateHex ( $canonicalURL );
        return $stringToSign;
    }

    private function calculateSignature($stringToSign) {
        $signatureKey = $this->getSignatureKey ( $this->secretKey, $this->currentDate, $this->regionName, $this->serviceName );
        $signature = hash_hmac ( "sha256", $stringToSign, $signatureKey, true );
        $strHexSignature = strtolower ( bin2hex ( $signature ) );
        return $strHexSignature;
    }

    public function getHeaders() {
        $this->awsHeaders ['x-amz-date'] = $this->xAmzDate;
        ksort ( $this->awsHeaders );

        // Step 1: CREATE A CANONICAL REQUEST
        $canonicalURL = $this->prepareCanonicalRequest ();

        // Step 2: CREATE THE STRING TO SIGN
        $stringToSign = $this->prepareStringToSign ( $canonicalURL );

        // Step 3: CALCULATE THE SIGNATURE
        $signature = $this->calculateSignature ( $stringToSign );

        // Step 4: CALCULATE AUTHORIZATION HEADER
        if ($signature) {
            $this->awsHeaders ['Authorization'] = $this->buildAuthorizationString ( $signature );
            return $this->awsHeaders;
        }
    }

    private function buildAuthorizationString($strSignature) {
        return $this->HMACAlgorithm . " " . "Credential=" . $this->accessKey . "/" . $this->getDate () . "/" . $this->regionName . "/" . $this->serviceName . "/" . $this->aws4Request . "," . "SignedHeaders=" . $this->strSignedHeader . "," . "Signature=" . $strSignature;
    }

    private function generateHex($data) {
        return strtolower ( bin2hex ( hash ( "sha256", $data, true ) ) );
    }

    private function getSignatureKey($key, $date, $regionName, $serviceName) {
        $kSecret = "AWS4" . $key;
        $kDate = hash_hmac ( "sha256", $date, $kSecret, true );
        $kRegion = hash_hmac ( "sha256", $regionName, $kDate, true );
        $kService = hash_hmac ( "sha256", $serviceName, $kRegion, true );
        $kSigning = hash_hmac ( "sha256", $this->aws4Request, $kService, true );

        return $kSigning;
    }

    private function getTimeStamp() {
        return gmdate ( "Ymd\THis\Z" );
    }

    private function getDate() {
        return gmdate ( "Ymd" );
    }
}
?>