
<!DOCTYPE HTML>


<html>

<head>
	<meta charset="utf-8" />
	<title>Dioptra - SVG generator</title>
</head>

<body>

	<div id="krъmilo">sentence</div>
	
	<?php
	
	if (isset($_GET["sent_id"]))
		$sent_id = $_GET["sent_id"];
	else
		$sent_id = "ivp4";

	$prev = '';
	$prev_id = $sent_id;
	$prev_used = false;
	
	$next = '';
	$next_id = $sent_id;
	$next_used = false;

	$lg = "cs";
	
	$src_cs = "dioptra_cs.txt";
	$src_gr = "dioptra_gr.txt";
	
	if (($handle_cs = fopen($src_cs, "r")) !== FALSE) {
		while (($data = fgetcsv($handle_cs, 0, "\t")) !== FALSE) {
			if (empty($data) == FALSE) 
				if (empty($data[8]) == FALSE) {
					
					if ($prev_id !== $sent_id && $prev_used == false && $data[8] == $sent_id) {
						$prev = $prev_id;
						$prev_used = true;
					}
			
					if ($next_id !== $sent_id && $prev_used == true && $next_used == false) {
						$next = $next_id;
						$next_used = true;
					}
					
					if ($sent_id !== "all" && $data[8] == $sent_id) {
						
						$prev_used = true;
						
					//	echo $data[1] . " ";
						$input[$lg][$data[0]]["id"] = $data[0];		// token/UD id
						$input[$lg][$data[0]]["text"] = $data[1];		// text
						$input[$lg][$data[0]]["pos"] = $data[3];		// PoS
						$input[$lg][$data[0]]["ncy"] = $data[6];		// UD-ncy
						$input[$lg][$data[0]]["type"] = $data[7];		// UD type
					
					}
					
					if ($data[8] !== "" && $data[8] !== "_") {
						$prev_id = $data[8];
						if ($prev_used == true && $next_used == false)
							$next_id = $data[8];
					}
				}
		}
	}
	fclose($handle_cs);
	
	echo "<script type='text/javascript'>
			var input = " . json_encode($input) . ";
			var lg = '" . $lg . "';
			var sent_id = '" . $sent_id . "';
			var prev = '" . $prev . "';
			var next = '" . $next . "';
		</script>";

	?>
	
	<button id="download">Download</button>
	
	<div style="text-align:center;">
		<svg id="warn" width=200 height=100></svg>
		<svg id="svgac" width=100 height=100 xmlns="http://www.w3.org/2000/svg"></svg>
		<svg id="test" width=100 height=100></svg>
	</div>
	
</body>

<script>

	// thx https://stackoverflow.com/questions/31649362/how-to-make-json-stringify-encode-non-ascii-characters-in-ascii-safe-escaped-for
	var json = JSON.stringify(input)
	json  = json.replace(/[\u007F-\uFFFF]/g, function(chr) {
		return "\\u" + ("0000" + chr.charCodeAt(0).toString(16)).substr(-4)
	});
	json = JSON.parse(json);
	console.log(json);

	var xpos = 0;
	var ypos = 10;
	var xdef = 80;
	var ydef = 80;
	var tokens = 1;
	var roots = [];
	var dependents = [];
	var depLevels = [];
	var sizes = [];
	var levels = 1;
	var srcLenght = 0
	var srcProc = 0;
	var fertig = false;
	var phantom = false;
		
	// erster Blick auf die Daten
	for (var token in json.cs) {
		
		// wieviele Tokens in dem Satz sind
		srcLenght = srcLenght + 1;
		// findet das Root (bzw. Roote)
		
		if (json.cs[token].ncy == "0")
			// Sätze ohne Verb bekommen ein fiktives Root
			if (json.cs[token].type.includes("orphan")) {
				if (phantom == false) {
					roots.push({
						id: "0",
						text: "no verb",
						pos: "",
						ncy: "1",
						type: "root"
					});
					srcLenght = srcLenght + 1;
					phantom = true;
				};
				if (json.cs[token].type.includes("root:"))
					json.cs[token].type = json.cs[token].type.replace("root:","");
			}
			else {
				roots.push(json.cs[token]);
				json.cs[token].used = true;
			};
		
		// fiktives Root bei einer Verb-Ellipse
		if (json.cs[token].type.includes("ellipsis")) {
			if (roots[0])
				if (phantom == false) {
					roots.push({
						id: "1",
						text: "ellipsis",
						pos: "",
						ncy: roots[0].id,
						type: "conj"
					});
					srcLenght = srcLenght + 1;
					phantom = true;
				};
			json.cs[token].type = json.cs[token].type.replace(":ellipsis","")
			json.cs[token].ncy = "1";
		};
		
		// Konjunkte gehen auf die gleiche Ebene wie ihre Heads
		for (var tok in json.cs)
			for (var root in roots)
				if (json.cs[tok].ncy == roots[root].id && json.cs[tok].used !== true)
					if (json.cs[tok].type == "conj" || json.cs[tok].type == "appos") {
						roots.push(json.cs[tok]);
						json.cs[tok].used = true;
					};
					
	};
	
	// ein Array mit Index fur die Bild-Funktion
	depLevels[0] = roots;
	// fur Statistik
	srcProc = srcProc + roots.length;
	
	// dann der Rest
	do {

		// zweite Ebene wird durch direkte Dependenten von Roots populiert
		if (levels == 1)
			getDeps(roots, dependents);
		// weitere schauen immer auf das vorherige Array
		else {
			let prevs = dependents;
			dependents = [];
			getDeps(prevs, dependents);
		};
		
		// Update fur Statistik
		srcProc = srcProc + dependents.length;
		
		// speichert die neu populierte Ebene
		if (dependents.length > 0)
			depLevels[levels] = dependents;
		levels++;
		
	// die Loop endet, sobald getDeps() nichts findet
	} while (dependents.length > 0)
	
	// rechnet die Breite jeder Ebene aus und bestimmt die längste
	var xfull = roots.length * 100;
	for (var lev in depLevels) {
		sizes[lev] = calculateWidth(depLevels[lev]);
		if (sizes[lev] > xfull)
			xfull = sizes[lev];
	};
	
	// passt das Bild wird auf die längste Ebene an
	document.getElementById("svgac").setAttribute("width", (xfull + 20));
	// console.log(xfull);

	// wird nicht mehr gebraucht
	document.getElementById("test").remove();
	
	// zeigt, welche Tokens ausgelassen wurden
	if (srcProc < srcLenght) {
		let warning = document.createElementNS("http://www.w3.org/2000/svg", "text");
		warning.textContent = "missing " + (srcLenght - srcProc) + " tokens";
		warning.setAttribute("x", 10);
		warning.setAttribute("y", 10);
		warning.setAttribute("font-weight", "bold");
		warning.setAttribute("fill", "red");
		warning.setAttribute("text-anchor", "left");
		document.getElementById("warn").appendChild(warning);
	}
	else
		document.getElementById("warn").remove()
	console.log("processed tokens: " + srcProc + "/" + srcLenght);

	// generiert die Viereck-Elemente mit Text nach einzelnen Ebenen
	for (var i = 0; i <= depLevels.length; i++)
		drawLevel(depLevels[i], i);
	
	// generiert die Linien zwischen Elementen
	for (var i = 0; i <= depLevels.length; i++)
		for (var ii = 0; ii <= depLevels.length; ii++)
			drawRelations(depLevels[i], depLevels[ii]);
	
	


	function getDeps(heads, deps) {

		for (var token in json.cs)
			for (var head in heads)
				if (json.cs[token].ncy == heads[head].id && json.cs[token].used !== true) {
					if (json.cs[token].type !== "conj" && json.cs[token].type !== "appos") {
						deps.push(json.cs[token]);
						json.cs[token].used = true;
						if (levels > 0)
							getConjs(json.cs[token], deps);
					};	
				};

	};
	
	function getConjs(head, conjs) {
		
		for (tok in json.cs)
			if (json.cs[tok].ncy == head.id && json.cs[tok].used !== true)
				if (json.cs[tok].type == "conj" || json.cs[tok].type == "appos") {
					conjs.push(json.cs[tok]);
					json.cs[tok].used = true;
					getConjs(json.cs[tok], conjs);
				};
				
	};
	
	function calculateWidth(content){
		
		let test = document.getElementById("test");
		
		if (typeof(content) !== "undefined")
			var width = content.length * 100;
		else
			return 0;
		
		for (var token in content) {
			
			xdef = 80;
		
			let type = document.createElementNS("http://www.w3.org/2000/svg", "text");
			type.textContent = content[token].type;
			type.setAttribute("text-anchor", "middle");
			test.appendChild(type);
			
			let text = document.createElementNS("http://www.w3.org/2000/svg", "text");
			text.textContent = content[token].text;
			text.setAttribute("font-weight", "bold");
			text.setAttribute("text-anchor", "middle");
			test.appendChild(text);
						
			let pos = document.createElementNS("http://www.w3.org/2000/svg", "text");
			pos.textContent = content[token].pos;
			pos.setAttribute("text-anchor", "middle");
			test.appendChild(pos);
			
			var typeSize = type.getComputedTextLength();
			var textSize = text.getComputedTextLength();
			var posSize = pos.getComputedTextLength();
			
			if (typeSize > xdef)
				xdef = typeSize + 20;
			if (textSize > xdef)
				xdef = textSize + 20;
			if (posSize > xdef)
				xdef = posSize + 20;
			
			if (xdef > 100)
				width = width + (xdef - 80);
			
		};
		
	//	console.log("level width: " + (width - 20));

		return width - 20;
		
	};

	function drawLevel(content, level) {
		
		let svg = document.getElementById("svgac");
		if (typeof(content) !== "undefined") {
		
			if (sizes[level] < xfull)
				xpos = 10 + (xfull - sizes[level]) / 2;
			else
				xpos = 10;
		
		};
		
		for (var token in content) {
			
			xdef = 80;
			ydef = 80;
			
			if (level > 0)
				ypos = level * 120;
			else
				ypos = level * 100;
			
			let rect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
			rect.setAttribute("x", xpos);
			rect.setAttribute("y", ypos);
			rect.setAttribute("fill", "white");
			rect.setAttribute("height", ydef);
			rect.setAttribute("rx", 2);
			rect.setAttribute("ry", 2);
			rect.setAttribute("stroke", "black");
			rect.setAttribute("stroke-width", 1);
			rect.setAttribute("opacity", 0.5);
			svg.appendChild(rect);

			let type = document.createElementNS("http://www.w3.org/2000/svg", "text");
			type.textContent = content[token].type;
			type.setAttribute("x", xpos + xdef/2);
			type.setAttribute("y", ypos + ydef/4);
			type.setAttribute("text-anchor", "middle");
			svg.appendChild(type);
			
			let text = document.createElementNS("http://www.w3.org/2000/svg", "text");
			text.textContent = content[token].text;
			text.setAttribute("x", xpos + xdef/2);
			text.setAttribute("y", ypos + ydef/2);
			if (text.textContent !== "no verb" && text.textContent !== "ellipsis")
				text.setAttribute("font-weight", "bold");
			text.setAttribute("text-anchor", "middle");
			svg.appendChild(text);
						
			let pos = document.createElementNS("http://www.w3.org/2000/svg", "text");
			pos.textContent = content[token].pos;
			pos.setAttribute("x", xpos + xdef/2);
			pos.setAttribute("y", ypos + ydef/1.33);
			pos.setAttribute("text-anchor", "middle");
			svg.appendChild(pos);
			
			var typeSize = type.getComputedTextLength();
			var textSize = text.getComputedTextLength();
			var posSize = pos.getComputedTextLength();
			
			if (typeSize > xdef)
				xdef = typeSize + 20;
			if (textSize > xdef)
				xdef = textSize + 20;
			if (posSize > xdef)
				xdef = posSize + 20;
			
			type.setAttribute("x", xpos + xdef/2);
			text.setAttribute("x", xpos + xdef/2);
			pos.setAttribute("x", xpos + xdef/2);
			
			xpos = xpos + xdef + 20;
			rect.setAttribute("width", xdef);
			
			content[token].xsize = xdef;
			content[token].xpos = xpos;
			content[token].ypos = ypos;
			
			if (ypos > ydef) {
				ypos = ypos + 100;
				svg.setAttribute("height", ypos);
				
			if (document.getElementById("warn"))
				document.getElementById("warn").setAttribute("height", ypos);
			};
		};
	};

	function drawRelations(heads, deps) {
		
		for (var dep in deps)
			for (var head in heads)				
				if (deps[dep].ncy == heads[head].id) 
					if (deps[dep].type !== "conj" && deps[dep].type !== "appos") {
						
						let line = document.createElementNS("http://www.w3.org/2000/svg", "line");
						line.setAttribute("stroke", "blue");
						line.setAttribute("x1", deps[dep].xpos - deps[dep].xsize/2 - 20);
						line.setAttribute("y1", deps[dep].ypos);
						line.setAttribute("x2", heads[head].xpos - heads[head].xsize/2 - 20);
						line.setAttribute("y2", heads[head].ypos + ydef);
						document.getElementById("svgac").appendChild(line);
					
					}
					else {
						
						if (deps[dep].type == "conj")
							var yoff = -14;
						else 
							var yoff = 7;
						
						let line = document.createElementNS("http://www.w3.org/2000/svg", "line");
						line.setAttribute("stroke", "blue");
						line.setAttribute("x1", deps[dep].xpos - deps[dep].xsize - 20);
						line.setAttribute("y1", deps[dep].ypos + ydef/2 + yoff);
						line.setAttribute("x2", heads[head].xpos - 20);
						line.setAttribute("y2", heads[head].ypos + ydef/2 + yoff);
						document.getElementById("svgac").appendChild(line);
					
					};
				
			
		
	};
	
	// thx https://stackoverflow.com/questions/60241398/how-to-download-and-svg-element-as-an-svg-file
	function download() {
		
		let svg = document.getElementById("svgac");
		const base64doc = btoa(unescape(encodeURIComponent(svg.outerHTML)));
		const a = document.createElement("a");
		const e = new MouseEvent("click");
		a.download = lg + "-" + sent_id + ".svg";
		a.href = 'data:image/svg+xml;base64,' + base64doc;
		a.hidden = true;
		a.dispatchEvent(e);
		
	};

	window.onload = function(){
		
		document.getElementById("krъmilo").innerHTML = "sentence " + sent_id;
		
		if (typeof prev !== "undefined")
			document.getElementById("krъmilo").innerHTML = document.getElementById("krъmilo").innerHTML + "<br/><a href='/svg.php?sent_id=" + prev + "' >previous</a> ";
		
		if (typeof next !== "undefined")
			document.getElementById("krъmilo").innerHTML = document.getElementById("krъmilo").innerHTML + "<br/><a href='/svg.php?sent_id=" + next + "' >next</a> ";
		
		document.getElementById("download").onclick = download;
		
	};

</script>

</html>