<?php

namespace Functions\api;

class uploadTiny
{
  static public function upload()
  {
    /***************************************************
     * Only these origins are allowed to upload images *
     ***************************************************/
    $accepted_origins = array("http://localhost:9502, https://blogmg.com.br, https://www.blogmg.com.br, https://teste.blogmg.com.br, https://www.teste.blogmg.com.br");

    /*********************************************
     * Change this line to set the upload folder *
     *********************************************/
    $imageFolder = "public/assets/images/upload/";
    $name = uniqid();

    if (isset($_SERVER['HTTP_ORIGIN'])) {
      // same-origin requests won't set an origin. If the origin is set, it must be valid.
      if (in_array($_SERVER['HTTP_ORIGIN'], $accepted_origins)) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
      } else {
        header("HTTP/1.1 403 Origin Denied");
        return "Origin Denied";
        return;
      }
    }

    // Don't attempt to process the upload on an OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
      header("Access-Control-Allow-Methods: POST, OPTIONS");
      header("Access-Control-Allow-Headers: Content-Type");
      return;
    }

    reset($_FILES);
    $temp = current($_FILES);
    if (is_uploaded_file($temp['tmp_name'])) {
      /*
      If your script needs to receive cookies, set images_upload_credentials : true in
      the configuration and enable the following two headers.
    */
      // header('Access-Control-Allow-Credentials: true');
      // header('P3P: CP="There is no P3P policy."');

      // Sanitize input
      if (preg_match("/([^\w\s\d\-_~,;:\[\]\(\).])|([\.]{2,})/", $temp['name'])) {
        header("HTTP/1.1 400 Invalid file name.");
        return "Invalid file name";
      }

      // Verify extension
      if (!in_array(strtolower(pathinfo($temp['name'], PATHINFO_EXTENSION)), array("gif", "jpg", "png"))) {
        header("HTTP/1.1 400 Invalid extension.");
        return "Invalid extension";
      }

      // Accept upload if there was no origin, or if it is an accepted origin
      $ext = pathinfo($temp['name'], PATHINFO_EXTENSION);
      $filetowrite = $imageFolder . $name . "." . $ext;
      if (move_uploaded_file($temp['tmp_name'], $filetowrite)) {
        return json_encode(array('location' => '\public/assets/images/upload/' . $name . "." . $ext));
      } else {
        return "Upload failed move";
      }
    } else {
      // Notify editor that the upload failed
      return "Upload failed";
    }
  }
}
