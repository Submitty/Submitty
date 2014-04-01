/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#ifndef ___STR_UTIL___
#define ___STR_UTIL___
#include <string>

static inline void string_trim_left_inplace(std::string &str)
{
    str.erase(0, str.find_first_not_of(' '));
}

static inline void string_trim_right_inplace(std::string &str)
{
    str.erase(str.find_last_not_of(' ') + 1, std::string::npos);
}

static inline std::string string_trim_left(const std::string &str)
{
    std::string::size_type pos = str.find_first_not_of(' ');
    if (pos == std::string::npos) return std::string();

    return str.substr(pos, std::string::npos);
}

static inline std::string string_trim_right(const std::string &str)
{
    std::string::size_type pos = str.find_last_not_of(' ');
    if (pos == std::string::npos) return std::string();

    return str.substr(0, pos + 1);
}

static inline std::string string_trim(const std::string& str)
{
    std::string::size_type pos1 = str.find_first_not_of(' ');
    if (pos1 == std::string::npos) return std::string();

    std::string::size_type pos2 = str.find_last_not_of(' ');
    if (pos2 == std::string::npos) return std::string();

    return str.substr(pos1 == std::string::npos ? 0 : pos1,
                      pos2 == std::string::npos ? (str.length() - 1) : (pos2 - pos1 + 1));
}

static inline void string_trim_inplace(std::string& str)
{
    std::string::size_type pos = str.find_last_not_of(' ');
    if(pos != std::string::npos) {
        str.erase(pos + 1);
        pos = str.find_first_not_of(' ');
        if(pos != std::string::npos) str.erase(0, pos);
    }
    else
        str.erase(str.begin(), str.end());
}

#endif //___STR_UTIL___
