var highlight = function(){

	// Change unranged ints to ranged ints
	// [1,2,3,6,7] => [[1,3],[6,7]]
	function enrange(ar){
		// console.log("enrange: ");

		var nar = [];
		var start = ar[0];
		for (var i = 1;i < ar.length;i++){
			if (ar[i] > ar[i-1] + 1){
				nar.push([start,ar[i-1]]);
				start = ar[i];
			}
		}
		// console.log("ar: "+ar);

		nar.push([start,ar[ar.length-1]]);
		// console.log("nar: "+nar);

		return nar;
	}
	function enrange_char(ar){
		// console.log("enrange_char: ");

		var nar = [];
		var start = ar[0];
		for (var i = 1;i < ar.length;i++){
			if (ar[i] > ar[i-1] + 1){
				nar.push([start,ar[i-1]+1]);
				start = ar[i];
			}
		}
		// console.log("ar: "+ar);

		nar.push([start, ar[ar.length-1]+1]);
		// console.log("nar: "+nar);

		return nar;
	}

	// Count characters up to word
	// ['a', 'mom', 'was'], 2 => 5
	// function characters_to_word(words, word_num){
	// 	var u = 0;
	// 	var spaces= 0;
	// 	for (var i = 0;i < word_num;i++){
	// 		if(!words[i] || words[i]==" "){
	// 			//u += 1;
	// 			spaces+=1;
	// 			word_num++;
	// 			console.log("SPACE HERE");
	// 		}
	// 		else{
	// 			u += words[i].length;
	// 		}
	// 		if(spaces==1){
	// 			word_num--;
	// 		}
	// 		console.log(" word "+i +" of "+word_num+" : "+words[i]+" count "+u);
	//
	// 	}
	// 	console.log("count: "+(u + word_num) );
	// 	return u + word_num ;
	// }
	function characters_to_word_begin(sentence, word_num){
		console.log("find start");
		var prev = " ";
		var w = -1;
		if(word_num == 0){
			for (var i = 0; i <sentence.length; i++){
				console.log("char "+i+ " is: "+sentence[i]);

				if (sentence[i] != " "){
					console.log("spaces until: "+i );

					return i;
				}
			}
			return sentence.length-1;
		}
		for (var i = 0; i <sentence.length; i++){
			console.log("count: "+w + " char: "+i );

			if (sentence[i] != " " && prev == " "){
				w++;
				if (w == word_num){
					return i;
				}
			}
			prev = sentence[i];
		}
		return sentence.length;
	}
	function characters_to_word_end(sentence, word_num){
		console.log("find end");
		var prev = " ";
		var w = -1;

		for (var i = 0; i <sentence.length; i++){
			console.log("count: "+w + " char: "+i );

			if (sentence[i] == " " && prev != " "){
				w++;
				if (w == word_num){
					return i;
				}
			}
			prev = sentence[i];
		}
		return sentence.length;
	}
	// Convert word range to character range
	// "a dog went to the store", [1,3] => [2,13]
	function word_to_character_range(sentence, word_range){
		var s = characters_to_word_begin(sentence, word_range[0]);
		var e = characters_to_word_end(sentence, word_range[1]);

		console.log("start: "+s + " end: "+e);

		return [s,e];
	}

	// return sorted ranges (by first number)
	function sort_ranges(ranges){
		return _.sortBy(ranges, function(a){
			return a[0];
		});
	}

	// Return true if there are overlapping ranges (presorted)
	function range_overlap(ranges){
		for (var i = 0;i < ranges.length-1;i++){
			if (ranges[i][1] > ranges[i+1][0])
				return true;
		}
		return false;
	}


	// Insert tags in character ranges in string
	function tagString(sentence, ranges, surrounds){

		ranges = sort_ranges(ranges);

		if (range_overlap(ranges)){
			console.log("ERROR: RANGE OVERLAP");
			return sentence;
		}

		var new_string = "";
		var i = 0;
		var cr = 0;
		while (i < sentence.length && cr < ranges.length){
			// console.log(i, ranges[cr][0]);
			var next = sentence.slice(i,ranges[cr][0]);
			new_string += next;
			i = ranges[cr][1];
			if (sentence.slice(i-1,i+1)==="\\r" || sentence.slice(i-1,i+1)==="^M"){
				console.log("TAB ERROR"+":"+sentence.slice(i-1,i+1)+":"+sentence.slice(ranges[cr][0],i+1)+";");
				next = sentence.slice(ranges[cr][0],i+1);
				i++;
			}
			else{
				next = sentence.slice(ranges[cr][0],i);
			}
			new_string += surrounds[cr][0] + next + surrounds[cr][1];
			cr++;
		}
		new_string += sentence.slice(i,sentence.length);
		return new_string;
	}

	// Surround string with tags
	function surround(string,tag_start_or_tagger,tag_end){
		if (!tag_end){
			return tag_start.start + string + tag_start.end;
		}else{
			return tag_start + string + tag_end;
		}
	}

	// Returns a tagger object with a start and end tag
	function tagger(changes){
		var class_string = "";
		for (var i = 0;i < arguments.length;i++){
			class_string += arguments[i];
		}
		return {
			"start" : "<div class='" + class_string + "'>",
			"end" : "</div>"
		};
	}

	// function test_highlight(){
	// 	var a = "a dog went to the store";
	// 	var b = [1,3];
	// 	var c = word_to_character_range(a,b);
	// 	console.log(c,a.slice(c[0],c[1]));
	// 	console.log(a,[b]);
	// 	console.log(tagString(a, [
	// 		word_to_character_range(a,[1,3]),
	// 		word_to_character_range(a,[4,5])
	// 	], [["<span>","</span>"],["<p>","</p>"]]));
	// 	console.log(sort_ranges([[5,7],[2,4]]));

	// }

	// test_highlight();

	return {
		enrange:enrange,
		enrange_char:enrange_char,
		characters_to_word_begin:characters_to_word_begin,
		characters_to_word_end:characters_to_word_end,
		word_to_character_range:word_to_character_range,
		sort_ranges:sort_ranges,
		range_overlap:range_overlap,
		tagString:tagString,
		surround:surround,
		tagger:tagger
	};
}();
