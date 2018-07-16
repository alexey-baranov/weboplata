<?php
/**
 * под Гидру с вебоплатой
 * SELECT accounts.N_ACCOUNT_ID                                    N_ACCOUNT_ID,          -- Идентификатор лицевого счёта
accounts.VC_ACCOUNT                                      VC_ACCOUNT,            -- Номер лицевого счёта
SI_USERS_PKG_S.GET_ACCOUNT_BALANCE_SUM(
num_N_ACCOUNT_ID => accounts.N_ACCOUNT_ID)             N_BALANCE,             -- Баланс лицевого счёта
accounts.N_SUBJECT_ID                                    N_CUSTOMER_ID,         -- Идентификатор абонента
base_subjects.VC_NAME                                    VC_CUSTOMER_NAME,      -- Наименование (ФИО) абонента
DECODE(base_subjects.N_SUBJ_TYPE_ID,
SYS_CONTEXT('CONST', 'SUBJ_TYPE_Company'), 1, 0)  B_IS_LEGAL,            -- Признак юридического лица
bs_addresses.VC_VISUAL_CODE                              VC_ADDRESS,            -- Фактический адрес абонента
subscriptions.VC_DOC_CODE                                VC_CONTRACT,           -- Номер и дата договора на оказание услуг
subscriptions.VC_SERVICE                                 VC_SERVICE_NAME,       -- Наименование услуги
services_state.VC_SERVICE_STATE_NAME                     VC_SERVICE_STATE_NAME  -- Состояние оказания услуги
FROM   SI_V_SUBJ_ACCOUNTS             accounts
INNER JOIN SI_V_SUBJECTS              customers
ON     customers.N_SUBJECT_ID               = accounts.N_SUBJECT_ID
INNER JOIN SI_V_SUBJECTS              base_subjects
ON     base_subjects.N_SUBJECT_ID           = customers.N_BASE_SUBJECT_ID
LEFT  JOIN SI_V_SUBJ_ADDRESSES_SIMPLE bs_addresses
ON     bs_addresses.N_SUBJECT_ID            = base_subjects.N_SUBJECT_ID
AND    bs_addresses.N_ADDR_TYPE_ID          = SYS_CONTEXT('CONST', 'ADDR_TYPE_FactPlace')
AND    bs_addresses.N_SUBJ_ADDR_TYPE_ID     = SYS_CONTEXT('CONST', 'BIND_ADDR_TYPE_Actual')
AND    bs_addresses.C_FL_MAIN               = 'Y'
LEFT  JOIN SI_V_SUBSCRIPTIONS         subscriptions
ON     subscriptions.N_ACCOUNT_ID           = accounts.N_ACCOUNT_ID
AND    subscriptions.N_CUSTOMER_ID          = customers.N_SUBJECT_ID
AND    subscriptions.N_PAR_SUBSCRIPTION_ID IS NULL
AND    subscriptions.D_BEGIN               <= SYSDATE
AND    subscriptions.C_FL_CLOSED            = 'N'
LEFT  JOIN RG_V_SERV_STATUS           services_state
ON     services_state.N_SUBSCRIPTION_ID     = subscriptions.N_SUBSCRIPTION_ID
WHERE  accounts.N_ACCOUNT_TYPE_ID           = SYS_CONTEXT('CONST', 'ACC_TYPE_Customer')
Для получения данных по конкретному абоненту (по идентификатору абонента), добавьте в конец запроса:
AND    customers.N_SUBJECT_ID               = 12345 -- Ограничение по идентификатору абонента (поле N_CUSTOMER_ID результата выборки)
Выборка данных по конкретному лицевому счёту (по идентификатору лицевого счёта), добавьте в конец запроса:
AND    accounts.N_ACCOUNT_ID                = 12345 -- Ограничение по идентификатору лицевого счёта (поле N_ACCOUNT_ID результата выборки)
Выборка данных по конкретному лицевому счёту (по номеру лицевого счёта):
AND    accounts.VC_ACCOUNT                  = '12345-И' -- Ограничение по номеру лицевого счёта (поле VC_ACCOUNT результата выборки)
 */

header("PRAGMA:NO-CACHE"); //страница не кешируется!!!
header("Access-Control-Allow-Origin: *"); //можно вызывать с любого домена

ini_set("display_errors","1");

require_once __DIR__ . '/../lib/bootstrap.php';

Logger::getRootLogger()->debug($_REQUEST);


header ("location:https://217.114.191.114:9443/weboplata/main?".$_SERVER["QUERY_STRING"]);