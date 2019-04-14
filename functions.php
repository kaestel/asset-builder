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
function debug($vars, $output = "print") {
	if(!is_array($vars)) {
		$vars = [$vars];
	}
	foreach($vars as $var) {
		if($output == "file") {
			writeToFile($var);
		}
		else {
			print_r($var);
			print "<br />\n";
		}
	}
}

// Check if web url points to valid ressource
function web_file_exists($url) {
	
	$context = stream_context_create(['http' => ['method' => 'HEAD']]);
	$headers = get_headers($url, 0, $context);
	return stripos($headers[0], "200 OK") ? true : false;

}

function makeDirRecursively($path) {
	if(!file_exists($path)) {
		$parts = explode("/", $path);
		$verify_path = "";
		for($i = 1; $i < count($parts); $i++) {
			$verify_path .= "/".$parts[$i];
			if(!file_exists($verify_path)) {
				mkdir($verify_path);
			}
		}
	}
}


function safeCopy($src, $dest) {
	global $domain;
	// h2("safeCopy:" . $src . ";". $dest . ":" . $domain);
	global $doc_root;

	$clean_src = preg_replace("/([\?#]{1}[^$]+)?/", "", $src);
	$clean_dest = preg_replace("/([\?#]{1}[^$]+)?/", "", $dest);

	makeDirRecursively(dirname($doc_root.$clean_dest));

	// is file available via filesystem
	if(file_exists($doc_root.$clean_src)) {

		// h2("copy directly:" . $doc_root.$clean_src . " -> " . $doc_root.$clean_dest);
		copy($doc_root.$clean_src, $doc_root.$clean_dest);
	}
	// copy file from web
	else {
		// h2("copy via web:" . $domain.$src . " -> " . $doc_root.$clean_dest);
		file_put_contents($doc_root.$clean_dest, file_get_contents($domain.$src));
	}

	
}


function parseJSFile($file, $fp) {
	global $js_include_size;
	global $domain;
	// global $doc_root;


	// If includes start with //, then prefix with http:
	if(preg_match("/^\/\//", $file)) {
		$file = "http:" . $file;
	}

	// normalize file path
	$file_fragments_raw = explode("/", $file);
	$file_fragments = [];
	foreach($file_fragments_raw as $folder) {
		if($folder == "..") {
			array_pop($file_fragments);
		}
		else {
			array_push($file_fragments, $folder);
		}
	}
	$file = implode("/", $file_fragments);


	$file_content = @file_get_contents($file);
	if($file_content === false) {

		print '<div class="file bad">'."\n";
		print "\t".'<h2 class="bad">'.$file.": Missing file</h2>\n";
		print '</div>';
		return;

	}

	$file_size = strlen($file_content);
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



				// Process line (unless comment switch is on)

				// check if line contains new include path
				// if it does, then continue iteration in included file and skip work line
				if(!$comment_switch && preg_match("/document.write[^$]+script[^$]+src=\"([a-zA-Z0-9\.\/_\:\-\=\?]+)\"/i", $work_line, $matches)) {
//					print "matched include:".$matches[1]."<br>";


					$work_line = "";
					$include_line = true;

					// external include
					if(preg_match("/(http[s]?:)?\/\//i", $matches[1])) {
						$filepath = $matches[1];
					}
					// local, absolute include
					else if(strpos($matches[1], "/") === 0) {
//						$filepath = $_SERVER["DOCUMENT_ROOT"].$matches[1];
						$filepath = $domain.$matches[1];
					}
					// relative include
					// JS include can only be relative if they are always included from same level dir
					// if relative path is found here, expect that is is relative to document root
					else {
						$filepath = $domain."/".$matches[1];
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
		print "\t".'<h2 class="warning">'.$file.': Empty file</h2>'."\n";
	}

	//	print "<div class=\"size\">($js_include_size bytes) -> ($minisize bytes)</div>";

	// end outer file wrapper
	print "</div>";

}




function parseCSSFile($file, $fp) {
	global $css_include_size;
	global $domain;
	// global $doc_root;
	global $css_output_path;
	global $css_output_relative_paths;
	global $variant;
	global $escaped_variant;


	// If includes start with //, then prefix with http:
	if(preg_match("/^\/\//", $file)) {
		$file = "http:" . $file;
	}

	// normalize file path
	$file_fragments_raw = explode("/", $file);
	$file_fragments = [];
	foreach($file_fragments_raw as $folder) {
		if($folder == "..") {
			array_pop($file_fragments);
		}
		else {
			array_push($file_fragments, $folder);
		}
	}
	$file = implode("/", $file_fragments);


	$file_content = @file_get_contents($file);
	if($file_content === false) {

		print '<div class="file bad">'."\n";
		print "\t".'<h2 class="bad">'.$file.": Missing file</h2>\n";
		print '</div>';
		return;

	}

	$file_size = strlen($file_content);
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
		$comment_block = "";

		foreach($lines as $linenumber => $line) {

			// adjustment string - modify this string, for reference in matches
			$work_line = $line;
			$include_line = false;
			// Not reduced parts to keep
			$but_keep = "";

			if($work_line) {

				// comment switch off and inline comment 
				// (is intended to catch any multi-inline comments, 
				// which might otherwise make the replacement pattern fail)
				//
				// replace one-liner comments, even if nested inside other string
				// if(!$comment_switch && preg_match("/\/\*(^\*\/)+\*\//", $work_line)) {
				if(!$comment_switch && preg_match("/\/\*[^$]+\*\//", $work_line)) {

					$temp_line = $work_line;
					while(strpos($temp_line, "/*") !== false && strpos($temp_line, "*/") !== false) {

						// Figure out the nuances
						$comment_start = strpos($temp_line, "/*");
						$comment_end = strpos($temp_line, "*/")+2;
					
						// $comment_block = substr($temp_line, $comment_start, $comment_end);
						// debug(["inline comment:", $temp_line, $work_line, $comment_start, $comment_end]);

						$inline_comment = substr($temp_line, $comment_start, $comment_end - $comment_start);
						$temp_line = str_replace($inline_comment, "", $temp_line);

						$comment_block .= $inline_comment."\n";
					}

					// Work line after replacing inline comments
					$work_line = $temp_line;
				}

				// comment switch off
				// found /* comment start
				if(!$comment_switch && strpos($work_line, "/*") !== false) {

					// print "Remove inline comment:".$temp_line . ", " . $work_line."<br>";
					$com_s_pos = strpos($line, "/*");
					$comment_switch = true;

					// Save comment block for further investigation (look for "license")
					$comment_block .= substr($line, $com_s_pos);

					// get line content before comment starts (if any)
					// $work_line = substr($line, 0, $com_s_pos);
					$but_keep = substr($line, 0, $com_s_pos);
					$work_line = substr($line, 0, $com_s_pos);
				}

				// comment switch on
				// look for */ comment end
				else if($comment_switch && strpos($work_line, "*/") !== false) {

					$com_e_pos = strpos($line, "*/");
					$comment_switch = false;

					// Reset comment block
					// $comment_block = "";
					$comment_block .= substr($line, 0, $com_e_pos+2);

					// get line content after comment is ended
					$work_line = substr($line, $com_e_pos+2);

				}

				// comment switch is on, remove all content
				else if($comment_switch) {

					$comment_block .= $work_line;

					$work_line = "";
					// $work_line = $but_keep;
				}

				// reset comment start and end position
				$com_s_pos = 0;
				$com_e_pos = 0;



				// check if line contains new include
				if(!$comment_switch && preg_match("/@import url\(['\"]?([a-zA-Z0-9\.\/_\:\-\=\?]+)['\"]?\)/i", $work_line, $matches)) {
					// debug(["matched include:",$matches[1]]);

					$include_line = true;

					// external include
					if(preg_match("/(http[s]?:)?\/\//i", $matches[1])) {
						$filepath = $matches[1];
					}
					// local, absolute include
					else if(strpos($matches[1], "/") === 0) {
						$filepath = $domain.$matches[1];
					}

					// relative include
					// should be relative to current file
					else {
						$filepath = dirname($file)."/".$matches[1];
					}

					// Is import really a tracker rather than a CSS import?
					if(preg_match("/^\/\//", $filepath) && !preg_match("/\.css$/", $filepath)) {
						 // debug(["I'm a tracker", $filepath]);
					}
					else {
						$work_line = "";

						// parse new include file
						parseCSSFile($filepath, $fp);
					}


					// add whitespace
					fwrite($fp, "\n");
				}


				if(!$comment_switch && $comment_block) {
					if (preg_match("/license|copyright/i", $comment_block)) {
						fwrite($fp, $comment_block."\n");
						$minisize += strlen($comment_block);
						// debug(["COMMENT BLOCK:", $comment_block]);
					}
					$comment_block = "";

				}


				if((trim($work_line) && !$comment_switch) || (trim($but_keep) && $comment_switch)) {


					// Additional assets linking via stylesheet
					// - Images and fonts

					// if located out of default image/font (/img, /css or /assets) location,
					// - it should be re-referenced and moved into assets folder
					

					// if located in default location (/img, /css or /assets),
					// - then update reference to match output file location

					// Look for image references url(*)
					// Look for font references url(*)
					if(preg_match_all("/url\([\'\"]?([^\'\"\)]+)[\'\"]?\)/", $work_line, $assets)) {

						// debug(["assets", $assets]);

						if(count($assets) == 2) {
							foreach($assets[1] as $asset) {

								// debug(["asset", $asset, !preg_match("/^(http[s]?:)?\/\/([^\/]+)/i", $asset, $domains), $domains]);


								// Don't change external assets references
								// But do update url's with hardcoded "same domain"

								// If it doesn't start width http, https or // OR has current domain in it
								// Then it must be a local reference which should be included in the build
								if(!preg_match("/^(http[s]?:)?\/\/([^\/]+)/i", $asset, $domains) || strpos($domains[0], $_SERVER["HTTP_HOST"]) !== false) {
									// debug(["match:", $asset, $domains]);


//									$asset_folder = explode("/", dirname(str_replace($_SERVER["DOCUMENT_ROOT"], "", $file)));

									$asset_folder = explode("/", dirname(str_replace($domain, "", $file)));
//									print "\nfile: $file<br>\n";
									// print_r($asset_folder);

									// make sure we have clean url (same url allowed)
									$asset = preg_replace("/^(http[s]?:\/\/)+[^\/]+/", "", $asset);
									$work_line = preg_replace("/^(http[s]?:\/\/)+[^\/]+/", "", $work_line);


									// Create normalized absolute path for asset validation
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



									// Check that asset exists, before continuing asset processing
									$header = get_headers($domain.$normalized_asset, 1);
									// Asset doesn't exist
									// print_r($header);
									if(
										!$header || 
										!isset($header[0]) || 
										!preg_match("/[123][0-9]{2}/", $header[0]) || 
										!preg_match("/image|css|font/", (is_array($header["Content-Type"]) ? $header["Content-Type"][count($header["Content-Type"])-1] : $header["Content-Type"]))
									) {
										h2("$asset: Local asset missing", "bad");
									}
									// Asset exists
									else {

										// h2("normalized_asset:" . $normalized_asset);


										// only consider moving assets that are not located in /img, /css or /assets
										// or /#variant#/css, /#variant#/img or /#variant#/assets
										if(
											(!$variant && !preg_match("/^\/(css|img|assets)\//", $normalized_asset)) 
											|| 
											($variant && !preg_match("/^(".$escaped_variant."\/css|".$escaped_variant."\/img|".$escaped_variant."\/assets)\//", $normalized_asset))
										) {


											// identify asset type

											// font asset
											// Put everything in one bowl – and keep the original folderstructure to maintain separation
											// TODO: weak spot for svg's - are they a graphic or a font 
											// (check for id for now - extrmely rarely used for graphic references)
											if(preg_match("/\.(woff[2]?|eot|eot|ttf|svg|otf)([\?#][^$]+)?$/i", $normalized_asset)) {
												$consolidated_asset = $variant."/assets".$normalized_asset;
											}
											// graphic asset
											else if(preg_match("/\.(jpg|gif|png|svg)([\?#][^$]+)?$/i", $normalized_asset)) {
												$consolidated_asset = $variant."/assets".$normalized_asset;
											}
											// unknown asset
											else {
												$consolidated_asset = $variant."/assets".$normalized_asset;

												h2("$normalized_asset: Unknown asset", "warning");
											}

											// h2("consolidated_asset:" . $consolidated_asset);

											// Copy asset
											safeCopy($normalized_asset, $consolidated_asset);


										}
										else {
											
											$consolidated_asset = $normalized_asset;
										}


										// Convert to relative paths
										if($css_output_relative_paths) {

											// Make relative asset url, to update CSS paths

											// Remove css fragment (linking happens from css folder)
											if(preg_match("/^(\/css|".$escaped_variant."\/css)\//i", $consolidated_asset)) {
												$relative_asset = preg_replace("/^(\/css|".$escaped_variant."\/css)\//i", "", $consolidated_asset);
											}
											// Make path relative to css folder
											else {
												$relative_asset = "..".preg_replace("/$escaped_variant/", "", $consolidated_asset);
											}


											// h2("relative_asset:" . $relative_asset);

											// Update the css reference
											$work_line = str_replace($asset, $relative_asset, $work_line);

										}
										// Use absolute paths
										else {

											// Update the css reference to normalized absolute path
											$work_line = str_replace($asset, $consolidated_asset, $work_line);
										}

									}

								}
								// Check external url
								// Don't process/consolidate external assets – but do check if they exist
								else {

									// Add http to // references
									if(preg_match("/^\/\//", $asset)) {
										$asset = "http:" . $asset;
									}

									// Try to get file, check response code
									// Notify about missing files 
									$header = get_headers($asset, 1);
									if(
										!$header || 
										!isset($header[0]) || 
										!preg_match("/[123][0-9]{2}/", $header[0]) || 
										!preg_match("/image|css|font/", (is_array($header["Content-Type"]) ? $header["Content-Type"][count($header["Content-Type"])-1] : $header["Content-Type"]))
									) {
										h2("$asset: External asset missing", "bad");
									}

								}

							}

						}

					}

					fwrite($fp, $work_line);
					$minisize += strlen($work_line);
				
				}



			}

			// output result/stats of parsing

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
		print "\t".'<h2 class="warning">'.$file.': Empty file</h2>'."\n";
	}

	// 		$_ .= $source . " ($include_size bytes) -> " . $file_output[$index] . " (".filesize($file_output[$index])." bytes)<br />";
	// 		$_ .= count($includes) . " include files<br /><br />";

	print "</div>";

}

