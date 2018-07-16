<?php
/**
 * Created by PhpStorm.
 * User: alexey_baranov
 * Date: 11.10.14
 * Time: 17:49
 */

namespace Weboplata;


use Billing\Core;

/**
 * sbrf - сбербанк онлайн
 */
class Weboplata {
    const OK = 0;
    const ERR_NO_ACCOUNT = 2;
    const ERR_NO_PHONE = 3;
    const ERR = 5;
    const ERR_REPEAT_EXTERNAL_PAYMENT_ID = 4;

    const ERR_NO_ACCOUNT_MESSAGE = "Неверный номер счета";
    const ERR_NO_PHONE_MESSAGE = "Неверный номер телефона";
    const ERR_MESSAGE = "Ошибка платежной системы";
    const ERR_REPEAT_EXTERNAL_PAYMENT_ID_MESSAGE = "Платеж с таким вшеншим идентификатором уже существует";

       /**
     * Номер счета по телефону
     *
     * @param string $phoneNumber номер телефона как в Биллинге 555999 !без тире!
        *
        * @throws Exception неверный номер телефона
     * @return string
     */
    function getAccountNumberByPhoneNumber($phoneNumber) {
            $st= Core::getEm()->getConnection()->prepare("
                    select a.Number
                    
                    from
                    account a
                    join Subscriber s on s.ID= a.SubscriberID
                    join serviceaccount sa  on a.id = sa.AccountID
                    join atsaccount ata on sa.id = ata.id
                    
                    where
                    Port = :phoneNumber
                    and (
                      s.IsLegal = 0
                      or s.IsLegal= 1 and a.Number like 'А%'
                      or a.Number like '555999%'
                    )
                    ");
        $st->bindValue(":phoneNumber", $phoneNumber);
        $st->execute();

        $result= $st->fetchColumn();

        if ($result===false){
            throw new Exception(Weboplata::ERR_NO_PHONE_MESSAGE, WebOplata::ERR_NO_PHONE);
        }

        \Logger::getRootLogger()->debug($phoneNumber."->".$result);

        return $result;
    }

    /**
     * Проверяет наличие счета или телефона
     *
     * @param string $AccountNumber номер счета / или телефона !без тире!
     * @param string $PSID платежная система
     * @param string $ServiceType номер услуги 7- телефон, 2- интернет, 3-
     *
     * @throws Exception неверный номер договора
     * @return string
     */
    function getMetadata($AccountNumber, $PSID, $ServiceType) {
        //вид услуги
        $ServiceType = (int)$ServiceType;
        //номер счета
        if ($ServiceType == 7) {
            $AccountNumber = $this->getAccountNumberByPhoneNumber($AccountNumber);
        }
        elseif ($ServiceType==8 && !preg_match('/\-./', $AccountNumber)){
            $AccountNumber.="-И";
            \Logger::getRootLogger()->info("К номеру счета добавлена -И: {$AccountNumber}");
        }

        $st = Core::getEm()->getConnection()->prepare("
select top 1
  a.Number as number,
  li.LegalAddress as address,
  tpn.Name as tariff,
  a.Saldo as saldo,
  s.IsLegal

from Subscriber s
  left join legalInfo li on li.SubscriberID=s.ID
  join Account a on a.SubscriberID= s.ID
  join ServiceAccount sa on sa.AccountID=a.ID
  join TariffPlanName tpn on tpn.ID = sa.TariffPlanNameID

where
  a.Number = :AccountNumber
  and (
      a.Number LIKE '%-И' AND sa.ServiceAccountType=3
      or a.Number LIKE '%-Т' AND sa.ServiceAccountType=101
  )
  and (
    s.IsLegal = 0
    or s.IsLegal= 1 and a.Number like 'А%'
    or a.Number like '555999%'
  )
");
        $st->bindParam(":AccountNumber", $AccountNumber);

        $st->execute();

        //на винде fetch почему-то не выполняется в случае добавления платежа. выдает ошибку "The active result for the query contains no fields"
        $metadataAsArray = $st->fetch();


        if ($metadataAsArray){
            return $metadataAsArray;
        }
        else if ($ServiceType==7){
            throw new Exception(Weboplata::ERR_NO_PHONE_MESSAGE, Weboplata::ERR_NO_PHONE);
        }
        else {
            throw new Exception(Weboplata::ERR_NO_ACCOUNT_MESSAGE, Weboplata::ERR_NO_ACCOUNT);
        }
    }

    /**
     * PaymentAddFromCSV
     *
     *
     * USE [billing35]
    GO
     *
     * DECLARE @d datetime = getdate()
     *
     * EXEC	[dbo].[PaymentAddfromCSV]
     * @AccountNumber = '789-И',
     * @PaymentSumm = 10,
     * @PaymentDate = @d,
     * @PSID = sngb,
     * @CurrencyCode = 810,
     * @txn_id = NULL,
     * @ExternalPaymentID = N'''5166876361521280''',
     * @PaymentNumber = NULL,
     * @PaymentType = 3

     * GO

     *
     * @param string $AccountNumber номер счета / или телефона !без тире!
     * @param string $PaymentSumm сумма
     * @param string $PaymentDate дата платежа
     * @param string $PSID платежная система
     * @param string $ServiceType номер услуги 7- телефон, 2- интернет, 3-
     * @param string $ExternalPaymentID номер платежа во внешней системе
     *
     * @throws \Exception 2-нет счета, 4-повторный $ExternalPaymentID, 5-др. ошибка
     *
     * @return int 0-ОК
     *
     */
    function add($AccountNumber, $PaymentSumm, $PaymentDate, $PSID, $ServiceType, $ExternalPaymentID)
    {
        //вид услуги
        $ServiceType = (int)$ServiceType;
        //номер счета
        if ($ServiceType == 7) {
            $AccountNumber = $this->getAccountNumberByPhoneNumber($AccountNumber);
        }
        elseif ($ServiceType==8 && !preg_match('/\-./', $AccountNumber)){
            $AccountNumber.="-И";
            \Logger::getRootLogger()->info("К номеру счета добавлена -И: {$AccountNumber}");
        }
        //сумма
        $PaymentSumm = (float)$PaymentSumm;
        //дата
        if (preg_match('/^\d+$/', $PaymentDate)){
            $tmp= new \DateTime();
            $tmp->setTimestamp($PaymentDate);
            $PaymentDate = $tmp;
            \Logger::getRootLogger()->debug("PaymentDate->".$PaymentDate->format(\DateTime::ISO8601));
        }
        else{
            $PaymentDate = \DateTime::createFromFormat('Y-m-d\TH:i:s', $PaymentDate);
        }
        $ExternalPaymentID = (string)$ExternalPaymentID;
        $CurrencyCode = 810;
        //шняга какая-то
        $txn_id = null;
        //по служебке №123490 сделал числом, а до этого был null
        $PaymentNumber = $PaymentDate->format("Hi");
        //тип платежа
        $PaymentType = 3;

        $st = Core::getEm()->getConnection()->prepare("exec PaymentAddFromCSV :AccountNumber, :PaymentSumm, :PaymentDate, :PSID, :CurrencyCode, :txn_id, :ExternalPaymentID,:PaymentNumber, :PaymentType");
        $Result = null;
//            $st->bindParam(":Result", $Result, \PDO::PARAM_INT, \PDO::PARAM_INPUT_OUTPUT);
        $st->bindParam(":AccountNumber", $AccountNumber);
        $st->bindParam(":PaymentSumm", $PaymentSumm);
        $st->bindParam(":PaymentDate", $PaymentDate->format("Y-m-d H:i:s"));
//            $st->bindParam(":PaymentDate", $PaymentDate="2014-10-11T20:00:00");
        $st->bindParam(":PSID", $PSID);
        $st->bindParam(":CurrencyCode", $CurrencyCode);
        $st->bindParam(":txn_id", $txn_id);
        $st->bindParam(":ExternalPaymentID", $ExternalPaymentID);
        $st->bindParam(":PaymentNumber", $PaymentNumber);
        $st->bindParam(":PaymentType", $PaymentType);


        $st->execute();

        $result = null;
        try {
            //на винде fetch почему-то не выполняется в случае добавления платежа. выдает ошибку "The active result for the query contains no fields"
            $result = $st->fetchColumn();
        } catch (\PDOException $ex) {
            if ($ex->getMessage() == "SQLSTATE[IMSSP]: The active result for the query contains no fields.") {
                return WebOplata::OK;
            } else {
                throw $ex;
            }
        }

        switch ($result) {
            case WebOplata::OK:
                return Weboplata::OK;
            case WebOplata::ERR_NO_ACCOUNT:
                throw new Exception(WebOplata::ERR_NO_ACCOUNT_MESSAGE, $result);
            case WebOplata::ERR_REPEAT_EXTERNAL_PAYMENT_ID:
                throw new Exception(WebOplata::ERR_REPEAT_EXTERNAL_PAYMENT_ID_MESSAGE, $result);
            default:
                throw new Exception(WebOplata::ERR_MESSAGE.": [{$result}]", $result);
        }
    }
}