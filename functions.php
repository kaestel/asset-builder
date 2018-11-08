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


function safeCopy($src, $dest, $domain) {
	global $doc_root;
	makeDirRecursively(dirname($dest));

	// is file available via filesystem
	if(file_exists($src)) {
		copy($src, $dest);
	}
	// copy file from web
	else {
		file_put_contents($dest, file_get_contents(str_replace($doc_root, $domain, $src)));
	}

	
}


function parseJSFile($file, $fp) {
	global $js_include_size;
	global $domain;
	global $doc_root;


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
					if(preg_match("/http[s]?:\/\//i", $matches[1])) {
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
		print "\t".'<h2 class="bad">'.$file.': Empty file</h2>'."\n";
	}

	//	print "<div class=\"size\">($js_include_size bytes) -> ($minisize bytes)</div>";

	// end outer file wrapper
	print "</div>";

}




function parseCSSFile($file, $fp) {
	global $css_include_size;
	global $domain;
	global $doc_root;
	global $css_output_path;


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
						// $filepath = $_SERVER["DOCUMENT_ROOT"].$matches[1];
						$filepath = $domain.$matches[1];
					}

					// relative include
					// should be relative to current file
					else {
						// $filepath = dirname($file)."/".$matches[1];
//						$filepath = $domain."/".$matches[1];
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

						if(count($assets) == 2) {
							foreach($assets[1] as $asset) {
								// print "match:" . $asset. "\n";

								// Don't change external assets references
								// But do update url's with hardcoded "same domain"

								// If it doesn't have http OR has current domain in it
								if(!preg_match("/http[s]?:\/\/([^\/]+)/i", $asset, $domains) || strpos($domains[0], $_SERVER["HTTP_HOST"]) !== false) {

									
//									$asset_folder = explode("/", dirname(str_replace($_SERVER["DOCUMENT_ROOT"], "", $file)));
									$asset_folder = explode("/", dirname(str_replace($domain, "", $file)));
									// print_r($asset_folder);

									// make sure we have clean url
									$asset = preg_replace("/(http[s]?:\/\/)+[^\/]+/", "", $asset);
									$work_line = preg_replace("/(http[s]?:\/\/)+[^\/]+/", "", $work_line);
									// print "\nasset: $asset<br>\n";

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

									// print "\nnormalized_asset:" . $normalized_asset . "\n";

									// Make asset url relative, to update CSS paths
									$relative_asset = preg_replace("/^\/css\//", "", $normalized_asset);
									$relative_asset = preg_replace("/^\/img\//", "../img/", $relative_asset);
									// css has already been removed, so now just remove assets
									$relative_asset = preg_replace("/^\/assets\//", "assets/", $relative_asset);
									// print "\nrelative_asset:" . $relative_asset . "\n";




									// font asset
									// TODO: weak spot for svg's - aare they a graphic or a font (check for id - rarely used for graphic references)
									if(preg_match("/\.(woff[2]?|eot|eot[\?]?#iefix|ttf|svg#[\-_A-Za-z0-9]+|otf)$/", $normalized_asset)) {


										$src = preg_replace("/(\.[wofetfsvg2]+)[\?#]+[\-_A-Za-z0-9]+$/", "$1", $doc_root.$normalized_asset);
										// Asset already in /css/fonts
										if(preg_match("/^\/css\/fonts\//", $normalized_asset)) {
											$dest = preg_replace("/(\.[wofetfsvg2]+)[\?#]+[\-_A-Za-z0-9]+$/", "$1", $doc_root.$normalized_asset);
										}
										// if path contains /css/fonts (use partial path)
										else if(preg_match("/\/css\/fonts\//", $normalized_asset)) {
											$dest = preg_replace("/(\.[wofetfsvg2]+)[\?#]+[\-_A-Za-z0-9]+$/", "$1", $doc_root.preg_replace("/[a-zA-Z0-9\-_.\/]+(\/css\/fonts\/)+/", "$1", $normalized_asset));
										}
										// Asset needs to be relocated
										else {
											$dest = preg_replace("/(\.[wofetfsvg2]+)[\?#]+[\-_A-Za-z0-9]+$/", "$1", $doc_root."/css/fonts/".basename($normalized_asset));
										}
										// print "src:" . $src . " -> dest " . $dest ."\n";

										// move fonts 
										// if they are not referenced in default relative location (fonts)
										// of if they dont exist in the default relative location
										if(!preg_match("/^fonts\//", $relative_asset) || !file_exists($dest)) {

											safeCopy($src, $dest, $domain);

											// update workline with new location
	 										$relative_asset = "fonts/".basename($normalized_asset);

										}
										// print "work_line:" . $work_line . "\n";
										// print "font\n<br>";
									}
									// graphic asset
									else if(preg_match("/\.(jpg|gif|png|svg)$/", $normalized_asset)) {

										$src = $doc_root.$normalized_asset;
										// Asset already in /img
										if(preg_match("/^\/img\//", $normalized_asset)) {
											$dest = $doc_root.$normalized_asset;
										}
										// if path contains /img/ (use partial path)
										else if(preg_match("/\/img\//", $normalized_asset)) {
											$dest = $doc_root.preg_replace("/[a-zA-Z0-9\-_.\/]+(\/img\/)+/", "$1", $normalized_asset);
										}
										// Asset needs to be relocated
										else {
											$dest = $doc_root."/img/".basename($normalized_asset);
										}

										// print "src:" . $src . " -> dest " . $dest ."\n";

										// move images if they are not in default location (../img)
										if(!preg_match("/^\.\.\/img\//", $relative_asset) || !file_exists($dest)) {

											// print "copy:" . $src . " -> " . $dest ."\n";

											safeCopy($src, $dest, $domain);

											// update workline with new location
	 										// $work_line = str_replace($normalized_asset, "../img/".basename($normalized_asset), $work_line);
	 										$relative_asset = "../img/".basename($normalized_asset);

										}
										// print "work_line:" . $work_line . "\n";
										// print "graphic\n<br>";
									}
									// unknown asset
									else {


										$src = $doc_root.$normalized_asset;
										// Asset already in /css/assets
										if(preg_match("/^\/css\/assets\//", $normalized_asset)) {
											$dest = $doc_root.$normalized_asset;
										}
										// if path contains /css/assets/ (use partial path)
										else if(preg_match("/\/css\/assets\//", $normalized_asset)) {
											$dest = $doc_root.preg_replace("/[a-zA-Z0-9\-_.\/]+(\/css\/assets\/)+/", "$1", $normalized_asset);
										}
										// Asset needs to be relocated
										else {
											$dest = $doc_root."/css/assets/".basename($normalized_asset);
										}
										// print "src:" . $src . " -> dest " . $dest ."\n";

										// move images if they are not in default location (/img)
										if(!preg_match("/^assets\//", $relative_asset) || !file_exists($dest)) {


											safeCopy($src, $dest, $domain);

											// update workline with new location
	 										$relative_asset = "css/assets/".basename($normalized_asset);
											
	 										// $work_line = str_replace($normalized_asset, "/css/assets/".basename($normalized_asset), $work_line);

										}

										// print "work_line:" . $work_line . "\n";
										// print "unknown asset\n<br>";
									}
									
									// Make sure include is relative to safe location
									$work_line = str_replace($asset, $relative_asset, $work_line);
									

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
		print "\t".'<h2 class="bad">'.$file.': Empty file</h2>'."\n";
	}

	// 		$_ .= $source . " ($include_size bytes) -> " . $file_output[$index] . " (".filesize($file_output[$index])." bytes)<br />";
	// 		$_ .= count($includes) . " include files<br /><br />";

	print "</div>";

}

