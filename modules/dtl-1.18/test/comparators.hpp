#ifndef DTL_COMPARATORS
#define DTL_COMPARATORS

class CaseInsensitive: public dtl::Compare<char> {
public:
    virtual bool impl(const char& a, const char& b) const {
        return tolower(a) == tolower(b);
    }
};

class StringCaseInsensitive: public dtl::Compare<string> {
public:
    virtual bool impl(string& a, string& b) const {
        if (a.length() == b.length()) {
            bool equal = (strncasecmp(a.c_str(), b.c_str(), a.length()) == 0);
            return equal;
        }
        else {
            return false;
        }
    }
};

#endif // DTL_COMPARATORS
