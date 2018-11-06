<?
function h1($text, $class = false) {
	print '<h1'.($class ? ' class="'.$class.'"' : '').'>'.$text.'</h1>';
}
function h2($text, $class = false) {
	print '<h2'.($class ? ' class="'.$class.'"' : '').'>'.$text.'</h2>';
}
function h3($text, $class = false) {
	print '<h3'.($class ? ' class="'.$class.'"' : '').'>'.$text.'</h3>';
}
function p($text, $class = false) {
	print '<p'.($class ? ' class="'.$class.'"' : '').'>'.$text.'</p>';
}
function div($text, $class = false) {
	print '<div'.($class ? ' class="'.$class.'"' : '').'>'.$text.'</div>';
}





function parseJSFile($file, $fp) {
	global $js_include_size;
//	global $js_input_path;


//	h2($file);

	$file_size = strlen(file_get_contents($file));
	$js_include_size += $file_size ? $file_size : 0;

	$minisize = 0;

	// file header
	print '<div class="file">'."\n";


	// get lines from file
	$lines = file($file);

	// if file has content
	if(count($lines)) {

		print "\t".'<h2 class="good" onclick="this.parentNode.className = this.parentNode.className.match(/open/) ? \'file\' : \'file open\'">'.$file."</h2>\n";

		fwrite($fp, "\n");
		fwrite($fp, "/*".basename($file)."*/\n");


//		print $file_size;

		$comment_switch = false;

		foreach($lines as $linenumber => $line) {

			// adjustment string - modify this string, for reference in matches
			$work_line = $line;
			$include_line = false;

			if($work_line) {

				// Deal with comments

				// remove one-liner /**/ comments, even if nested inside other string
				if(!$comment_switch && preg_match("/\/\*[^$]+\*\//", $work_line)) {
					$work_line = preg_replace("/\/\*[^$]+\*\//", "", $work_line);
				}

				// found for /* comment start
				if(!$comment_switch && strpos($work_line, "/*") !== false) {

					$com_s_pos = strpos($line, "/*");
					$comment_switch = true;

					// get line content before comment starts (if any)
					$work_line = substr($line, 0, $com_s_pos);
				}

				// comment switch is on, look for */ comment end
				if($comment_switch && strpos($work_line, "*/") !== false) {

					$com_e_pos = strpos($line, "*/");
					$comment_switch = false;

					// get line content after comment is ended
					$work_line = substr($line, $com_e_pos+2);

					$com_s_pos = 0;
					$com_e_pos = 0;
				}
				// comment switch is on, remove all content
				else if($comment_switch) {
					
					$work_line = "";
				}

				// check for // comment starts the line
				// skip work line
				if(!$comment_switch && preg_match("/^\/\//", $work_line)) {

					$work_line = "";
				}

				// check for // comment start position within line
				// ignore if // is inside quoted string or in regular expression 
				if(!$comment_switch && preg_match_all("/[^:\\\]{1}\/\//", $work_line, $matches)) {

					// multiple matches to be investigated
					if(count($matches[0]) > 1) {

//						print "multiple occurences<br>";

						for($i = 0; $i < count($matches); $i++) {

							// start with last occurence
							$pos = strrpos($work_line, "//");

							// add new newline, because we are get substring from begining only
							$additional_test = substr($work_line, 0, $pos)."\n";

							// check if removal breaks quoted string or quoted string was already broken
							if(
								(substr_count($additional_test, '"')%2 === 0 || substr_count($work_line, '"')%2 === 1) 
								&& 
								(substr_count($additional_test, "'")%2 === 0 || substr_count($work_line, "'")%2 === 1)
							) {
								$work_line = $additional_test;
							}

						}

					}
					// only one occurence
					else {
						// remove from occurence to end
						$additional_test = preg_replace("/\/\/.*/", "", $work_line);

						// check if removal breaks quoted string or quoted string was already broken
						if(
							(substr_count($additional_test, '"')%2 === 0 || substr_count($work_line, '"')%2 === 1) 
							&& 
							(substr_count($additional_test, "'")%2 === 0 || substr_count($work_line, "'")%2 === 1)
						) {
							$work_line = $additional_test;
						}
					}


				}



				// not comment and not empty line - nothing should be done
				// else if(!$comment_switch && trim($work_line)) {
				// 
				// 	$work_line = $line;
				// }


				// Process line (unless comment switch is on)

				// TODO: make sure it is a script tag

				// check if line contains new include path
				// if it does, then continue iteration in included file and skip work line
				if(!$comment_switch && preg_match("/document.write[^$]+script[^$]+src=\"([a-zA-Z0-9\.\/_\:\-\=\?]+)\"/i", $work_line, $matches)) {
//					print "matched include:".$matches[1]."<br>";


					$work_line = "";
					$include_line = true;

					// external include
					if(preg_match("/http[s]?:\/\//i", $matches[1])) {
						$filepath = $matches[1];
					}
					// local, absolute include
					else if(strpos($matches[1], "/") === 0) {
						$filepath = $_SERVER["DOCUMENT_ROOT"].$matches[1];
					}
					// relative include
					// JS include can only be relative if they are always included from same level dir
					// if relative path is found here, expect that is is relative to document root
					else {
						$filepath = $_SERVER["DOCUMENT_ROOT"]."/".$matches[1];
					}

					// parse new include file
					parseJSFile($filepath, $fp);

					// add whitespace
					fwrite($fp, "\n");
				}

				// If work line still contains data, then add it to the output file
				if(trim($work_line) && !$comment_switch) {
					fwrite($fp, $work_line);
					$minisize += strlen($work_line);
				
				}

			}


			// output result/stats of parsing

			// No change was made
			if(!$comment_switch && (trim($work_line) && trim($line) == trim($work_line))) {
				print "\t".'<div class="notminified"><code>'.$linenumber.':'.htmlentities($line).'</code></div>';
			}
			// Change was made and it is not an include line
			else if(!$include_line) {
				print "\t".'<div class="minified"><span class="bad">'.$linenumber.':'.htmlentities(trim($line)).'</span><span class="good">'.htmlentities(trim($work_line)).'</span></div>';
			}

		}

	}

	// empty files
	else {
		print "\t".'<div class="minified"><span class="bad">Empty file</span></div>'."\n";
	}

	//	print "<div class=\"size\">($js_include_size bytes) -> ($minisize bytes)</div>";

	// end outer file wrapper
	print "</div>";

}




function parseCSSFile($file, $fp) {
	global $css_include_size;

	$file_size = strlen(file_get_contents($file));
	$css_include_size += $file_size ? $file_size : 0;
	$minisize = 0;

	// file header
	print '<div class="file">'."\n";
	print "\t".'<h2 class="good" onclick="this.parentNode.className = this.parentNode.className.match(/open/) ? \'file\' : \'file open\'">'.$file."</h2>\n";

	// get lines from file
	$lines = file($file);

	// if file has content
	if(count($lines)) {

		fwrite($fp, "\n");
		fwrite($fp, "/*".basename($file)."*/\n");


//		print $file_size;

		$comment_switch = false;

		foreach($lines as $linenumber => $line) {

			// adjustment string - modify this string, for reference in matches
			$work_line = $line;
			$include_line = false;

			if($work_line) {

				// replace one-liner comments, even if nested inside other string
				if(!$comment_switch && preg_match("/\/\*[^$]+\*\//", $work_line)) {
					$work_line = preg_replace("/\/\*[^$]+\*\//", "", $work_line);
				}

				// found for /* comment start
				if(!$comment_switch && strpos($work_line, "/*") !== false) {

					$com_s_pos = strpos($line, "/*");
					$comment_switch = true;

					// get line content before comment starts (if any)
					$work_line = substr($line, 0, $com_s_pos);
				}

				// comment switch is on, look for */ comment end
				if($comment_switch && strpos($work_line, "*/") !== false) {

					$com_e_pos = strpos($line, "*/");
					$comment_switch = false;

					// get line content after comment is ended
					$work_line = substr($line, $com_e_pos+2);

				}
				// comment switch is on, remove all content
				else if($comment_switch) {
					
					$work_line = "";
				}

				// reset comment start and end position
				$com_s_pos = 0;
				$com_e_pos = 0;

				// check for // comment start - easy match, only ignore if : in front of //
				// if(!$comment_switch && preg_match("/(^|[^:])\/\//", $work_line) && !substr_count($work_line, '"')%2) {
				// 
				// 	$work_line = substr($line, 0, strpos($line, "//"))."\n";
				// }

				// not comment and not empty line - nothing should be done
				// else if(!$comment_switch && trim($work_line)) {
				// 
				// 	$work_line = $line;
				// }


				// check if line contains new include
				if(!$comment_switch && preg_match("/@import url\(([a-zA-Z0-9\.\/_\:\-\=\?]+)\)/i", $work_line, $matches)) {
//					print "matched include:".$matches[1]."<br>";

					$work_line = "";
					$include_line = true;

					// external include
					if(preg_match("/http[s]?:\/\//i", $matches[1])) {
						$filepath = $matches[1];
					}
					// local, absolute include
					else if(strpos($matches[1], "/") === 0) {
						$filepath = $_SERVER["DOCUMENT_ROOT"].$matches[1];
					}

					// relative include
					// should be relative to current file
					else {
						$filepath = dirname($file)."/".$matches[1];
					}

					// parse new include file
					parseCSSFile($filepath, $fp);

					// add whitespace
					fwrite($fp, "\n");
				}

				if(trim($work_line) && !$comment_switch) {


					// Additional assets, that should be re-referenced and moved?

					// Look for image references url(*)
					// Look for font references url(*)
					if(preg_match_all("/url\([\'\"]?([^\'\"\)]+)[\'\"]?\)/", $work_line, $assets)) {
//						$asset_url = str_replace($_SERVER["DOCUMENT_ROOT"], "", $file);

// print_r($matches);
						// print "file:" . $file . "<br>\n";;
						// print "DR:" . $_SERVER["DOCUMENT_ROOT"] . "<br>\n";;
//						print "asset_folder:" . $asset_folder . "<br>\n";
						if(count($assets) == 2) {
							foreach($assets[1] as $asset) {
								// print "match:" . $asset. "\n";

								// Don't change external assets references
								// But do update url's with hardcoded "same domain"

								// If it doesn't have http OR has current domain in it
								if(!preg_match("/http[s]?:\/\/([^\/]+)/i", $asset, $domains) || strpos($domains[0], $_SERVER["HTTP_HOST"]) !== false) {

									$asset_folder = explode("/", dirname(str_replace($_SERVER["DOCUMENT_ROOT"], "", $file)));
									
									// make sure we have clean url
									$asset = preg_replace("/(http[s]?:\/\/)+[^\/]+/", "", $asset);
									$work_line = preg_replace("/(http[s]?:\/\/)+[^\/]+/", "", $work_line);

									// if path is not absolute
									if(!preg_match("/^\//", $asset)) {
										// Simplest url normalization
										$reference = explode("/", $asset);
										foreach($reference as $fragment) {
											if($fragment == "..") {
												array_shift($reference);
												array_pop($asset_folder);
											}
										}

										$normalized_asset = implode("/", $asset_folder) ."/". implode("/", $reference);
									}
									else {
										$normalized_asset = $asset;
									}

									// font asset
									// TODO: weak spot for svg's - aare they a graphic or a font (check for id - rarely used for graphic references)
									if(preg_match("/\.(woff[2]?|eot|eot[\?]?#iefix|ttf|svg#[\-_A-Za-z0-9]+|otf)$/", $normalized_asset)) {

										// Make sure include is absolute to safe font location
										$work_line = str_replace($asset, $normalized_asset, $work_line);

										// move fonts if they are not in default location (/css/fonts)
										if(!preg_match("/^\/css\/fonts\//", $normalized_asset)) {
											 // print "copy:" . preg_replace("/.svg[#\-_A-Za-z0-9]*$/", ".svg", $_SERVER["DOCUMENT_ROOT"].$normalized_asset) . " -> " . preg_replace("/.svg[#\-_A-Za-z0-9]*$/", ".svg", $_SERVER["DOCUMENT_ROOT"]."/css/fonts/".basename($normalized_asset))."\n";
											copy(preg_replace("/.svg[#\-_A-Za-z0-9]*$/", ".svg", $_SERVER["DOCUMENT_ROOT"].$normalized_asset), preg_replace("/.svg[#\-_A-Za-z0-9]*$/", ".svg", $_SERVER["DOCUMENT_ROOT"]."/css/fonts/".basename($normalized_asset)));
											// update workline with new location
	 										$work_line = str_replace($normalized_asset, "/css/fonts/".basename($normalized_asset), $work_line);
										}
										// print "work_line:" . $work_line . "\n";
										// print "font\n<br>";
									}
									// graphic asset
									else if(preg_match("/\.(jpg|gif|png|svg)$/", $normalized_asset)) {

										// Make sure include is absolute to safe font location
										$work_line = str_replace($asset, $normalized_asset, $work_line);

										// move images if they are not in default location (/img)
										if(!preg_match("/^\/img\//", $normalized_asset)) {

											// print "copy:" . $_SERVER["DOCUMENT_ROOT"].$normalized_asset . " -> " . $_SERVER["DOCUMENT_ROOT"]."/img/".basename($normalized_asset)."\n";
											copy($_SERVER["DOCUMENT_ROOT"].$normalized_asset, $_SERVER["DOCUMENT_ROOT"]."/img/".basename($normalized_asset));
											// update workline with new location
	 										$work_line = str_replace($normalized_asset, "/img/".basename($normalized_asset), $work_line);

										}
										// print "work_line:" . $work_line . "\n";
										// print "graphic\n<br>";
									}
									// unknown asset
									else {

										// Make sure include is absolute to safe font location
										$work_line = str_replace($asset, $normalized_asset, $work_line);

										// move images if they are not in default location (/img)
										if(!preg_match("/^\/assets\//", $normalized_asset)) {

											// print "copy:" . $_SERVER["DOCUMENT_ROOT"].$normalized_asset . " -> " . $_SERVER["DOCUMENT_ROOT"]."/assets/".basename($normalized_asset)."\n";
											copy($_SERVER["DOCUMENT_ROOT"].$normalized_asset, $_SERVER["DOCUMENT_ROOT"]."/assets/".basename($normalized_asset));
											// update workline with new location
	 										$work_line = str_replace($normalized_asset, "/css/assets/".basename($normalized_asset), $work_line);

										}

										// print "work_line:" . $work_line . "\n";
										// print "unknown asset\n<br>";
									}


									// get location of asset
									// $url = "http://".$_SERVER["HTTP_HOST"].$normalized_asset;
									// print "URL:" . $url . "<br>\n";
//									print $match . " - " . dirname($file) . "<br>\n";


								}
							}
							

						}

//						print_r($matches);

					}





					fwrite($fp, $work_line);
					$minisize += strlen($work_line);
				
				}
			}


			// output result of parsing
			if(!$comment_switch && (trim($work_line) && trim($line) == trim($work_line))) {
				print "\t".'<div class="notminified"><code>'.$linenumber.':'.htmlentities($line).'</code></div>';
			}
			else if(!$include_line) {
				print "\t".'<div class="minified"><span class="bad">'.$linenumber.':'.htmlentities(trim($line)).'</span><span class="good">'.htmlentities(trim($work_line)).'</span></div>';
			}

		}

	}
	// empty files
	else {
		print "\t".'<div class="minified"><span class="bad">Empty file</span></div>'."\n";
	}

	// 		$_ .= $source . " ($include_size bytes) -> " . $file_output[$index] . " (".filesize($file_output[$index])." bytes)<br />";
	// 		$_ .= count($includes) . " include files<br /><br />";

	print "</div>";

}

