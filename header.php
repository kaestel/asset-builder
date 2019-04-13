<!DOCTYPE html>
<html lang="da">
<head>
	<!-- Read the licence - https://parentnode.dk //-->
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Building assets</title>
	<style type="text/css">
		* {font-family: monaco;}
		h1 {margin: 0; padding: 0; font-size: 20px;}
		h2 {margin: 0; padding: 0; font-size: 16px;}
		h3 {margin: 0; padding: 0; font-size: 13px;}
		.paths {border: 2px solid #26962d; padding: 2px 2px 0; border-radius: 2px; margin-bottom: 20px;}
		.paths h2 {padding: 3px 7px; margin: 0 0 2px; border-radius: 2px;}
		.paths h2 > span {display: inline-block; width: 155px;}
		.paths h2 > span + span {width: auto;}
		.paths h2.good {background-color: #26962d; color: white;}
		.paths h2.bad {background-color: #df0000; color: white;}
		.paths h2.warning {background-color: orange; color: white;}
		.includes h2.good {background-color: #26962d; color: white;}
		.includes h2.bad {background-color: #df0000; color: white;}
		.includes h2.warning {background-color: orange; color: white;}
		.includes .file .file h2 {font-size: 13px; background: #666666; color: white;}
		.includes .file .file h2.bad {background: #df0000;}
		.includes .file .file h2.warning {background: orange;}
		.includes {border: 2px solid #26962d; padding: 2px 2px 0; border-radius: 2px; margin-bottom: 20px;}
		.includes h2 {cursor: pointer; padding: 3px 7px; margin: 0 0 2px; border-radius: 2px;}
		.good {color: green;}
		.bad {color: red;}
		.warning {color: orange;}
		.notminified {color: green; font-weight: normal; font-size: 11px; padding: 0 0 0 0px; display: block;}
		.minified {color: green; font-weight: normal; font-size: 11px; padding: 0 0 0 0px; display: block;}
		.includes .file .file .minified {padding-left: 20px;}
		.includes .file .file .notminified {padding-left: 20px;}
		.file {color: black; font-weight: bold; font-size: 0;}
		.file div span,
		.file div code,
		.file div h2.good {display: none;}
		div.open > h2:first-child {opacity: 0.6;}
		.open > div > span,
		.open > div > code,
		.open > div > h2.good {display: block;}
	</style>
</head>
<body>