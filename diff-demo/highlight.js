// Change unranged ints to ranged ints
// [1,2,3,6,7] => [[1,3],[6,7]]
function enrange(ar){
	var nar = [];
	var start = ar[0];
	for (var i = 1;i < ar.length;i++){
		if (ar[i] > ar[i-1] + 1){
			nar.push([start,ar[i-1]]);
			start = ar[i];
		}
	}
	nar.push([start,ar[ar.length-1]]);
	return nar;
}

// Count characters up to word
// ['a', 'mom', 'was'], 2 => 5
function characters_to_word(words, word_num){
	var u = 0;
	for (var i = 0;i < word_num;i++){
		u += words[i].length;
	}
	return u + word_num;
}

// Convert word range to character range
// "a dog went to the store", [1,3] => [2,13]
function word_to_character_range(sentence, word_range){
	var initial_space_count = 0;
	for (var i = 0; i <sentence.length; i++){
		if (sentence[i] != " "){
			initial_space_count = i;
			sentence = sentence.slice(i,sentence.length);
			break;
		}
	}
	var words = sentence.split(" ");
	return [
		initial_space_count + characters_to_word(words, word_range[0]),
		initial_space_count + (word_range[1] < words.length ? characters_to_word(words, word_range[1]) + words[word_range[1]].length : 
			characters_to_word(words, words.length-1) + words[words.length-1].length)
	];
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
		console.log(i, ranges[cr][0]);
		var next = sentence.slice(i,ranges[cr][0]);
		new_string += next;
		i = ranges[cr][1];
		next = sentence.slice(ranges[cr][0],i);
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