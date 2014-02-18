#ifndef DTL_PRINTERS
#define DTL_PRINTERS

template <typename sesElem, typename stream = ostream >
class customChangePrinter : public Printer < sesElem, stream >
{
public :
    customChangePrinter () : Printer < sesElem, stream > () {}
    customChangePrinter (stream& out) : Printer < sesElem, stream > (out) {}
    ~customChangePrinter () {}
    void operator() (const sesElem& se) const {
        switch (se.second.type) {
        case SES_ADD:
            this->out_ << "Add: " << se.first << endl;
            break;
        case SES_DELETE:
            this->out_ << "Delete: " << se.first << endl;
            break;
        case SES_COMMON:
            this->out_ << "Common: " << se.first << endl;
            break;
        }
    }
};

#endif // DTL_PRINTERS
