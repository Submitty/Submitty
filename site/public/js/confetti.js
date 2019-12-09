const pieces = [];
const numberOfPieces = 2500;
const x_const = 0.25;
const max_times = 250;
const size_const = 10;
const gravity_const = 0.25;
const colors = [];
let month;

class Piece {
  constructor(x, y) {
    this.x = x;
    this.y = y;
    this.x_vel = (Math.random() - 0.5) * x_const;
    this.size = (Math.random() * 0.5 + 0.75) * size_const;
    this.gravity = (Math.random() * 0.5 + 0.75) * gravity_const;
    this.rotation = (Math.PI * 2) * Math.random();
    this.rotationSpeed = (Math.PI * 2) * (Math.random() - 0.5) * 0.0015;
    this.color = colors[Math.floor(Math.random() * colors.length)];
  }
}

const isMonthChanged = (prevMonth) => {
  const date_box = document.getElementById("submission_timestamp");
  let newMonth;
  if(typeof(date_box) != 'undefined' && date_box != null)
    submission_date = date_box.innerHTML.match(/\d+/g);

  //if we parsed the submission due date, use that instead
  if(submission_date.length >= 1){
    newMonth = parseInt(submission_date[0], 10) - 1;
  }
  else{
    newMonth = new Date().getMonth();
  }
  return prevMonth !== newMonth;
};

const setColorsArray = (month) => {
  //JS month : 0-11
  switch(month){
    case 0: //jan
      colors.push('#406bc9','#ffffff','#809bce','#9ac8de','#b6c7be');
      break;
    case 1: //feb
      colors.push('#df3b57','#ee4b6a','#7d2335','#86cec5','#b2e6f1');
      break;
    case 2: //mar
      colors.push('#8db62f','#7b9233','#034121','#022607','#ffcc00');
      break;
    case 3: //apr
      colors.push('#eed149','#3bca8b','#9ee0e7','#ebb8aa','#ffffff');
      break;
    case 4: //may
      colors.push('#f9eae5','#f16878','#c1dbb3','#7ebc89','#ff8154');
      break;
    case 5: //jun
      colors.push('#ec4067','#f4d35e','#f78764','#00889f','#083d77');
      break;
    case 6: //jul
      colors.push("#ffffff",'#de1a1a','#090c9b');
      break;
    case 7: //aug
      colors.push('#f0a202','#ff4040','#f2c940','#ab2321');
      break;
    case 8: //sept
            //sky blue,  submitty blue, shail green,  yellow,     red,  open-books purple
      colors.push('#8FD7FF', '#316498', '#34CA34', '#FFFF40', '#FF2929', '#9c84a4');
      break;
    case 9: //oct
      colors.push('#000000','#ff6700','#291528');
      break;
    case 10://nov
      colors.push('#5a351e','#522b47','#912f09','#f0a202','#fbf5f3');
      break;
    case 11://dec
      colors.push('#d7cdcc','#f7b11d','#1f5e00','#de1a1a','#ffffff');
      break;
    //make sure we have a default if parsing goes wrong
    default:
      colors.push('#8FD7FF', '#316498', '#34CA34', '#FFFF40', '#FF2929', '#9c84a4');
  }
};

const addConfetti =() => {
  var canvas = document.getElementById('confetti_canvas');
  if(!canvas)
    return;
  let times_ran = 0;

  const removeCanvas = () => {
    if(canvas.style.display !== "none"){
      canvas.style.display = "none";
      clearInterval(updateTimer);
      canvas.removeEventListener('click',removeCanvas);
    }
  };

  //destroy the canvas animation on click or on enter
  canvas.addEventListener("click", removeCanvas);
  window.addEventListener("keypress", (e) => {
    if(e.code === 'Enter' && canvas.style.display !== "none"){
      canvas.style.display = "none";
      console.log('clearing interval ', t);
      clearInterval(updateTimer);
      canvas.removeEventListener('click',removeCanvas);
    }
  });

  canvas.width  = window.innerWidth;
  let body = document.body;
  let html = document.documentElement;
  canvas.height = Math.max( body.scrollHeight, body.offsetHeight,
    html.clientHeight, html.scrollHeight, html.offsetHeight );

  canvas.style.display = "block";

  let ctx = canvas.getContext('2d');

  let lastUpdateTime = Date.now();

  if(isMonthChanged(month)) {
    setColorsArray(month);
  }

  const draw = () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    pieces.forEach((piece) => {
      ctx.save();

      ctx.fillStyle = piece.color;

      ctx.translate(piece.x + piece.size / 10, piece.y + piece.size / 2);
      ctx.rotate(piece.rotation);

      ctx.fillRect(-piece.size / 2, -piece.size / 2, piece.size, piece.size);

      ctx.restore();
    });

  };
  const update = () => {
    let now = Date.now(),
      dt = now - lastUpdateTime;

    for (let i = pieces.length - 1; i >= 0; i--) {
      let piece = pieces[i];

      if (piece.y > canvas.height) {
        pieces.splice(i, 1);
        continue;
      }

      piece.y += piece.gravity * dt;
      piece.rotation += piece.rotationSpeed * dt;
      piece.x += piece.x_vel;
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
      clearInterval(updateTimer);
      canvas.removeEventListener('click',removeCanvas);
    }
    draw();
  };

  while (pieces.length < numberOfPieces) {
    pieces.push(new Piece(Math.random() * canvas.width, Math.random() * canvas.height));
  }

  let updateTimer = setInterval(update, 10);
  draw();
};
