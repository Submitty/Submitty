function addConfetti(){
	var canvas = document.getElementById('confetti_canvas');
	if(!canvas)
		return;
	let times_ran = 0;

	canvas.width  = window.outerWidth;
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

	function randomColor () {
				//    dark blue  sky blue   shail green yellow    red      open-books purple
	    let colors = ['#0F47B6', '#8FD7FF', '#34CA34', '#FFFF40', '#FF2929', '#9c84a4'];
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
	        p.x += Math.random() * x_const;
	    }

	    while (pieces.length < numberOfPieces && times_ran < max_times) {
	        pieces.push(new Piece(Math.random() * canvas.width, -20));
	    }

	    lastUpdateTime = now;

	    times_ran ++;
	    let done = false;
	    if(times_ran >= max_times * 10){
	    	done = true;
	    	ctx.clearRect(0, 0, canvas.width, canvas.height);
	    	canvas.style.display = "";
	    	canvas.width = 0;
	    	canvas.height = 0;
	    	return;
	    }

	    if(!done)
	   		setTimeout(update, 1);
	}

	function draw () {
	    ctx.clearRect(0, 0, canvas.width, canvas.height);

	    pieces.forEach(function (p) {
	        ctx.save();

	        ctx.fillStyle = p.color;

	        ctx.translate(p.x + p.size / 25, p.y + p.size / 2);
	        ctx.rotate(p.rotation);

	        ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);

	        ctx.restore();
	    });

	    requestAnimationFrame(draw);
	}

	function Piece (x, y) {
	    this.x = x;
	    this.y = y;
	    this.size = (Math.random() * 0.5 + 0.75) * size_const;
	    this.gravity = (Math.random() * 0.5 + 0.75) * gravity_const;
	    this.rotation = (Math.PI * 2) * Math.random();
	    this.rotationSpeed = (Math.PI * 2) * (Math.random() - 0.5) * 0.0015;
	    this.color = randomColor();
	}

	while (pieces.length < numberOfPieces) {
	    pieces.push(new Piece(Math.random() * canvas.width, Math.random() * canvas.height));
	}
	update();
	draw();
}