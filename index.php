<?php
// Asset builder for parentNode webstack
// 2013-2018 Martin Kaestel Nielsen, parentnode.dk under MIT-License
// https://parentnode.dk
//
// Add options:
// - to use absolute or relative paths when building (default = relative)
// - to move assets to /img|css|js/assets (default = ture)


ini_set("auto_detect_line_endings", true);
error_reporting(E_ALL);

$access_item = false;
if(isset($read_access) && $read_access) {
	return;
}



include("functions.php");
include("header.php");





$variant = "";
$js_input_path = false;
$js_output_path = false;
$css_input_path = false;
$css_output_path = false;

// Should output use relative or absolute paths – relative is default
$css_output_relative_paths = true;


// Building variant?
if(isset($_GET["path"]) && $_GET["path"]) {
	// get params
	$variant = "/".$_GET["path"];

}

// Output relative CSS paths
if(isset($_GET["use_relative_css_paths"])) {
	// get params
	$css_output_relative_paths = $_GET["use_relative_css_paths"];

}

$escaped_variant = str_replace("/", "\/", $variant);

// Use document root starting point
$doc_root = $_SERVER["DOCUMENT_ROOT"];

// Current domain - used to resolve references relying on apache alias'
$domain = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"];




print '<div class="paths">';

h2("<span>DOMAIN:</span> <span>$domain</span>", "good");


// Does JS input path exist
if(file_exists("$doc_root".($variant ? "$variant" : "")."/js/lib")) {

	$js_input_path = "$doc_root".($variant ? "$variant" : "")."/js/lib";
	$js_output_path = "$doc_root".($variant ? "$variant" : "")."/js";

	h2("<span>JS INPUT PATH:</span> <span>$js_input_path</span>", "good");
	h2("<span>JS OUTPUT PATH:</span> <span>$js_output_path</span>", "good");

}
else {

	h2("No JS path?", "bad");

}

// Does CSS input path exist
if(file_exists("$doc_root".($variant ? "$variant" : "")."/css/lib")) {

	$css_input_path = "$doc_root".($variant ? "$variant" : "")."/css/lib";
	$css_output_path = "$doc_root".($variant ? "$variant" : "")."/css";

	h2("<span>CSS INPUT PATH:</span> <span>$css_input_path</span>", "good");
	h2("<span>CSS OUTPUT PATH:</span> <span>$css_output_path</span>", "good");

}
else {

	h2("No CSS path?", "bad");

}

// End of paths
print '</div>';



h1("JavaScript");
// List 1st level include files
print '<div class="includes js">';


if($js_input_path && $js_output_path) {

	// include license text
	$js_license_file = $js_input_path."/license.txt";

	// find include sources
	$js_handle = opendir("$js_input_path");
	while(($js_file = readdir($js_handle)) !== false) {
		if(preg_match("/^([a-zA-Z\-_]+)[_\-]include.js$/", $js_file, $match)) {

			// use http requests to get input sources (to allow for apache alias')
			$js_file_include[] = $domain . str_replace($_SERVER["DOCUMENT_ROOT"], "", $js_input_path)."/".$match[1]."_include.js";

			// use file system paths for output (because we need to write to the file system in a fixed location)
			$js_file_output[] = $js_output_path."/".$match[1].".js";
		}
	}


	// loop through segment includes
	foreach($js_file_include as $index => $source) {

		// print $source."<br>";
		// is segment include available
		if(!web_file_exists($source)) {

			p($source . " -> " . $js_file_output[$index]);
			p("No include file", "error");

		}
		else {

			// create output file
			$js_fp = @fopen($js_file_output[$index], "w+");

			// could not create, exit with error
			if(!$js_fp) {
				p("Make files writable (".$js_file_output[$index].")", "error");
				exit;
			}

			fwrite($js_fp, "/*\n");
			// include license
			if(file_exists($js_license_file)) {
				fwrite($js_fp, file_get_contents($js_license_file)."\n");
			}
			fwrite($js_fp, "asset-builder @ ".date("Y-m-d H:i:s")."\n");
			fwrite($js_fp, "*/\n");


			// keep track of file size
			$js_include_size = 0;


			// write compiled js
			parseJSFile($source, $js_fp);

			// Close file pointer
			fclose($js_fp);
		}

	}

}
// No js
else {

	h2("No JS to build", "warning");

}

// End of js includes
print '</div>';



h1("CSS");
// List 1st level include files
print '<div class="includes css">';

if($css_input_path && $css_output_path) {


	// include license text
	$css_license_file = $css_input_path."/license.txt";

	// find include sources
	$css_handle = opendir("$css_input_path");
	while(($css_file = readdir($css_handle)) !== false) {
		if(preg_match("/^([a-zA-Z\-_]+)[_\-]include.css$/", $css_file, $match)) {

			// use http requests to get input sources (to allow for apache alias')
			$css_file_include[] = $domain . str_replace($_SERVER["DOCUMENT_ROOT"], "", $css_input_path)."/".$match[1]."_include.css";

			// use file system paths for output (because we need to write to the file system in a fixed location)
			$css_file_output[] = $css_output_path."/".$match[1].".css";
		}
	}


	foreach($css_file_include as $index => $source) {

		// print $source .":".realpath($source);
		// is segment include available
		if(!web_file_exists($source)) {

			p($source . " -> " . $css_file_output[$index]);
			p("No include file", "error");

		}
		else {

			// create output file
			$css_fp = @fopen($css_file_output[$index], "w+");

			// could not create, exit with error
			if(!$css_fp) {
				p("Make files writable (".$css_file_output[$index].")", "error");
				exit;
			}

			fwrite($css_fp, "/*\n");
			// include license
			if(file_exists($css_license_file)) {
				fwrite($css_fp, file_get_contents($css_license_file)."\n");
			}
			fwrite($css_fp, "asset-builder @ ".date("Y-m-d H:i:s")."\n");
			fwrite($css_fp, "*/\n");


			// keep track of file size
			$css_include_size = 0;


			// write compiled js
			parseCSSFile($source, $css_fp);

			// Close file pointer
			fclose($css_fp);

		}

	}

}
// No css
else {

	h2("No CSS to build", "warning");

}

// End of css includes
print '</div>';


include("footer.php");



