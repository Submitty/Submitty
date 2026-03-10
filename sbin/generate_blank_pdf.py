from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import letter

def generate_template():
    path = "/var/local/submitty/courses/placeholder.pdf"
    c = canvas.Canvas(path, pagesize=letter)
    c.setFont("Helvetica", 12)
    c.drawString(72, 700, "This is a Submitty-generated pdf for grading purposes.")
    c.showPage()
    c.save()

if __name__ == "__main__":
    generate_template()