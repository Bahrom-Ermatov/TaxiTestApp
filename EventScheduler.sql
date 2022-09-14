CREATE EVENT IF NOT EXISTS update_company_balance
ON SCHEDULE EVERY 1 MINUTE
STARTS CURRENT_TIMESTAMP
ENDS CURRENT_TIMESTAMP + INTERVAL 15 HOUR
ON COMPLETION PRESERVE
DO
	call update_company_balance;


--------------------------------------------------------------------------
CREATE DEFINER=`root`@`localhost` PROCEDURE `update_company_balance`()
begin
	DECLARE finished INTEGER DEFAULT 0;
	DECLARE l_id integer;
	DECLARE l_change_balance float DEFAULT 0;
	DECLARE l_change_sum float DEFAULT 0;
    declare l_err_msg varchar(2000);
    
    DECLARE ch_balance_cur CURSOR FOR SELECT t.id, t.change_sum FROM change_balance_company t where t.company_id = 1;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
		ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
        @p2 = MESSAGE_TEXT;
		SELECT @p2 AS message;
    END;

	-- declare NOT FOUND handler
	DECLARE CONTINUE HANDLER 
        FOR NOT FOUND SET finished = 1;
        
    open ch_balance_cur;
    get_list: loop
		fetch ch_balance_cur into l_id, l_change_balance;
        if finished = 1 then
			leave get_list;
        end if;
		#Суммируем изменение баланса
        SET l_change_sum = l_change_sum + l_change_balance;
		
		#Удаляем запись из таблицы
		DELETE FROM change_balance_company
        where id = l_id;        

    end loop get_list;
    close ch_balance_cur;

	#Добавляем все изменения к балансу компании
	update companies t
    set t.balance = t.balance + l_change_sum
    where t.id = 1;

	commit;

    select l_change_sum;
end
