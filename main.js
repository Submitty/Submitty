window.onload = function(){
	diff.load("3 copies of \"Into the Wild\" added\n2 copies of \"Forrest Gump\" added\n2 copies of \"Gone with the Wind\" added\n1 copy of \"Raiders of the Lost Ark\" added\n3 copies of \"Toy Story\" added\nnew customer: Carol Adams\nnew customer: Kim Smith\nnew customer: Wayne Evans\nShip DVDs\n  Carol Adams receives \"Raiders of the Lost Ark\"\n  Kim Smith receives \"Into the Wild\"\n  Carol Adams receives \"Gone with the Wind\"\n  Kim Smith receives \"Gone with the Wind\"\n  Carol Adams receives \"Forrest Gump\"\nCarol Adams returns \"Forrest Gump\"\nKim Smith returns \"Into the Wild\"\nShip DVDs\n  Carol Adams receives \"Into the Wild\"\nCarol Adams returns \"Into the Wild\"\nShip DVDs\n  Carol Adams receives \"Toy Story\"\nCarol Adams has 3 movies:\n    \"Raiders of the Lost Ark\"\n    \"Gone with the Wind\"\n    \"Toy Story\"\nWayne Evans has no movies\n  preference list:\n    \"Raiders of the Lost Ark\"\n\"Toy Story\":\n  1 copy checked out and 2 copies available\n\"Gone with the Wind\":\n  2 copies checked out\n",
		"3 copies of \"Into the Wild\" added\n2 copies of \"Forrest Gump\" added\n2 copies of \"Gone with the Wind\" added\n1 copy of \"Raiders of the Lost Ark\" added\n3 copies wrong of \"ToyStory\" added\nnew customer: Carol Bad Apple Adams\nnew customer: Kim Smith\nnew customer: Wayne Evans\nShip DVDs\n  Carol Adams receives \"Raiders of the Lost Ark\"\n  Kim Smith receives \"Into the Wild\"\n  Carol Adams receives \"Gone with the Wind\"\n  Kim Smith receives \"Gone with the Wind\"\n  Carol Adams receives \"Forrest Gump\"\nCarol Adams returns \"Forrest Gump\"\nKim Smith returns \"Into the Wild\"\nShip DVDs\n  Carol Adams receives \"Into the Wild\"\nCarol Adams returns \"Into the Wild\"\nShip DVDs\n  Carol Adams \"Toy Story\"\nCarol Adams has no movies:\n    \"Raiders of the Lost Ark\"\n    \"Gone with the Wind\"\n    \"Toy Story\"\nWayne Evans has no movies\n  preference list:\n    \"Raiders of the Lost Ark\"\n\"Toy Story\":\n  1 copy checked out and 2 copies available\n\"Gone with the Wind\":\n  2 copies checked out\n"
	);
	differences = [
    {
        "student":
        {
            "start": 4,
            "line": [
                {
                    "line_number": 4,
                    "word_number":[ 2, 4 ]
                }, {
                    "line_number": 5,
                    "word_number":[ 3, 4 ]
                }
            ]
        },
        "instructor":
        {
            "start":4,
            "line": [
                {
                    "line_number": 4,
                    "word_number":[ 3, 4 ]
                }, {
                    "line_number": 5
                }
            ]
        }
    }, {
        "student":
        {
            "start": 20,
            "line": [
                {
                    "line_number": 20
                }, {
                    "line_number": 21,
                    "word_number":[ 3 ]
                }
            ]
        },
        "instructor":
        {
            "start":20,
            "line": [
                {
                    "line_number": 20,
                    "word_number":[ 2 ]
                }, {
                    "line_number": 21,
                    "word_number":[ 3 ]
                }
            ]
        }
    }
	];
	diff.evalDifferences(differences);
	diff.display();
};