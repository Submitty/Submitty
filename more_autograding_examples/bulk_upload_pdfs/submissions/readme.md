### This directory contains a list of submissions used for testing bulk uploads

* qr_six_page_ contains 36 pages with a QR code every 6 pages
* qr_prefix_ is the same length with the prefix 'sample_data' encoded on every QR
* qr_stress_test_ contains QR code placed in varying lengths and has a prefix and suffix that contains URL components

### 04_numeric_ids.txt

04_numeric_ids.txt is a list of fake numeric_ids that are shown in 04_numeric_id_scan.pdf
and is used to test the bulk upload OCR scanner feature. Some of the ids in this txt file are
associated with the user_id of one of the fake generated test users. When adding a
new fake user yml, please use one of the ids in this file for their numeric_id, and put the
user_id next to the numeric_id in the txt file.