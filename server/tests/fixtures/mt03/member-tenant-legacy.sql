INSERT INTO pa_member
  (id, sn, account, password, nickname, mobile, status, balance, user_money, create_time, update_time)
VALUES
  (11, 'M-LEGACY-11', 'same-account', '', 'Legacy member', '13800000000', 1, 10.00, 10.00, 1, 1);
INSERT INTO pa_member_tag (id, name, remark, create_time, update_time)
VALUES (21, 'same-tag', 'legacy', 1, 1);
INSERT INTO pa_member_tag_relation (id, member_id, tag_id) VALUES (31, 11, 21);
INSERT INTO pa_member_balance_log
  (id, sn, member_id, change_object, change_type, action, change_amount, left_amount, after_amount, source_type, source_sn, remark, admin_id, create_time)
VALUES
  (41, 'FLOW-SAME', 11, 1, 100, 1, 10.00, 10.00, 10.00, 0, 'SOURCE-SAME', 'legacy', 0, 1);
