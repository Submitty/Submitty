function addConfetti(){
	var canvas = document.getElementById('confetti_canvas');
	if(!canvas)
		return;
	let times_ran = 0;
	let frame = 0;

	//destroy the canvas animation on click or on enter
	canvas.addEventListener("click", function(){
		if(canvas.style.display != "none"){
			canvas.style.display = "none";
			return;
		}
	});
	window.addEventListener("keypress", function(e){
		if(e.code === "Enter" && canvas.style.display != "none"){
			canvas.style.display = "none";
			return;
		}
	});

	canvas.width  = window.innerWidth;
	var body = document.body;
    var html = document.documentElement;
	canvas.height = Math.max( body.scrollHeight, body.offsetHeight,
                       		  html.clientHeight, html.scrollHeight, html.offsetHeight );

	canvas.style.display = "block";

	let ctx = canvas.getContext('2d');
	let pieces = [];
	let numberOfPieces = 2500;
	let lastUpdateTime = Date.now();
	let x_const = 0.25;
	let max_times = 250;
	let size_const = 10;
	let gravity_const = 0.25;

	let date_box = document.getElementById("submission_timestamp");
	if(typeof(date_box) != 'undefined' && date_box != null)
		submission_date = date_box.innerHTML.match(/\d+/g);

	let d = new Date();
	let month = d.getMonth();

	//if we parsed the submission due date, use that instead
	if(submission_date.length >= 1){
		month = parseInt(submission_date[0], 10) - 1;
	}

	function randomColor () {
		let colors = [];

		//JS month : 0-11
		switch(month){
			case 0: //jan
				colors = ['#406bc9','#ffffff','#809bce','#9ac8de','#b6c7be'];
			break;
			case 1: //feb
				colors = ['#df3b57','#ee4b6a','#7d2335','#86cec5','#b2e6f1'];
			break;
			case 2: //mar
				colors = ['#8db62f','#7b9233','#034121','#022607','#ffcc00'];
			break;
			case 3: //apr
				colors = ['#eed149','#3bca8b','#9ee0e7','#ebb8aa','#ffffff'];
			break;
			case 4: //may
				colors = ['#f9eae5','#f16878','#c1dbb3','#7ebc89','#ff8154'];
			break;
			case 5: //jun
				colors = ['#ec4067','#f4d35e','#f78764','#00889f','#083d77'];
			break;
			case 6: //jul
				colors = ["#ffffff",'#de1a1a','#090c9b'];
			break;
			case 7: //aug
				colors = ['#f0a202','#ff4040','#f2c940','#ab2321'];
			break;
			case 8: //sept
				    //sky blue,  submitty blue, shail green,  yellow,     red,  open-books purple
				colors = ['#8FD7FF', '#316498', '#34CA34', '#FFFF40', '#FF2929', '#9c84a4'];
			break;
			case 9: //oct
				colors = ['#000000','#ff6700','#291528'];
			break;
			case 10://nov
				colors = ['#5a351e','#522b47','#912f09','#f0a202','#fbf5f3'];
			break;
			case 11://dec
				colors = ['#d7cdcc','#f7b11d','#1f5e00','#de1a1a','#ffffff'];
			break;
      //make sure we have a default if parsing goes wrong
      default:
        colors = ['#8FD7FF', '#316498', '#34CA34', '#FFFF40', '#FF2929', '#9c84a4'];

    }
    return colors[Math.floor(Math.random() * colors.length)];
	}

	function update () {
	    let now = Date.now(),
	        dt = now - lastUpdateTime;

	    for (let i = pieces.length - 1; i >= 0; i--) {
	        let p = pieces[i];

	        if (p.y > canvas.height) {
	            pieces.splice(i, 1);
	            continue;
	        }

	        p.y += p.gravity * dt;
	        p.rotation += p.rotationSpeed * dt;
	        p.x += p.x_vel;
	    }

	    while (pieces.length < numberOfPieces && times_ran < max_times) {
	        pieces.push(new Piece(Math.random() * canvas.width, -20));
	    }

	    lastUpdateTime = now;

	    times_ran ++;

	    if(times_ran >= max_times * 10){
	    	ctx.clearRect(0, 0, canvas.width, canvas.height);
	    	canvas.style.display = "";
	    	canvas.width = 0;
	    	canvas.height = 0;
	    }

	}

	function draw () {

    if(canvas.style.display === "none"){
      cancelAnimationFrame(frame);
      return;
    }

    update();
    ctx.clearRect(0, 0, canvas.width, canvas.height);

	    pieces.forEach(function (p) {
	        ctx.save();

	        ctx.fillStyle = p.color;

	        ctx.translate(p.x + p.size / 25, p.y + p.size / 2);
	        ctx.rotate(p.rotation);

	        ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);

	        ctx.restore();
	    });

	    frame = requestAnimationFrame(draw);
	}

	function Piece (x, y) {
	    this.x = x;
	    this.y = y;
	    this.x_vel = (Math.random() - 0.5) * x_const;
	    this.size = (Math.random() * 0.5 + 0.75) * size_const;
	    this.gravity = (Math.random() * 0.5 + 0.75) * gravity_const;
	    this.rotation = (Math.PI * 2) * Math.random();
	    this.rotationSpeed = (Math.PI * 2) * (Math.random() - 0.5) * 0.0015;
	    this.color = randomColor();
	}

	while (pieces.length < numberOfPieces) {
	    pieces.push(new Piece(Math.random() * canvas.width, Math.random() * canvas.height));
	}

	draw();
}
