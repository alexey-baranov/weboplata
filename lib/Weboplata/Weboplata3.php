<?php
/**
 * Created by PhpStorm.
 * User: alexey_baranov
 * Date: 11.10.14
 * Time: 17:49
 *
 * Под Гидру
 */

namespace Weboplata;


use Billing\Core;

/**
 * sbrf - сбербанк онлайн
 */
class Weboplata3
{
  const OK = 0;
  const ERR_NO_ACCOUNT = 2;
  const ERR_NO_PHONE = 3;
  const ERR = 5;
  const ERR_REPEAT_EXTERNAL_PAYMENT_ID = 7;

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
  function getAccountNumberByPhoneNumber($phoneNumber)
  {
    $st = Core::getEm()->getConnection()->prepare("
                    SELECT a.Number
                    
                    FROM
                    account a
                    JOIN Subscriber s ON s.ID= a.SubscriberID
                    JOIN serviceaccount sa  ON a.id = sa.AccountID
                    JOIN atsaccount ata ON sa.id = ata.id
                    
                    WHERE
                    Port = :phoneNumber
                    AND (
                      s.IsLegal = 0
                      OR s.IsLegal= 1 AND a.Number LIKE 'А%'
                      OR a.Number LIKE '555999%'
                    )
                    ");
    $st->bindValue(":phoneNumber", $phoneNumber);
    $st->execute();

    $result = $st->fetchColumn();

    if ($result === false) {
      throw new Exception(Weboplata::ERR_NO_PHONE_MESSAGE, WebOplata::ERR_NO_PHONE);
    }

    \Logger::getRootLogger()->debug($phoneNumber . "->" . $result);

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
  function getMetadata($AccountNumber, $PSID, $ServiceType)
  {
    //вид услуги
    $ServiceType = (int)$ServiceType;
    //номер счета
    if ($ServiceType == 7) {
      $AccountNumber = $this->getAccountNumberByPhoneNumber($AccountNumber);
    }

    /*
        http://217.114.191.114:9443/hydra/main/
        ?command=check&txn_id=1234567890&txn_date=20180416123744&
        account=1001&sum=12.34&bank_code=Сбербанк&to_account=Сбербанк'
    */
    $client = new \Zend_Rest_Client("http://217.114.191.114:9443/hydra/main/");

    $result= $client->check()
      ->txn_id(777)
      ->txn_date((new \DateTime())->format("YmdHis"))
      ->account($AccountNumber)
      ->sum(1)
      ->bank_code($PSID)
      ->to_account($PSID)
      ->get();


    if ($result) {
      return $result;
    } else if ($ServiceType == 7) {
      throw new Exception(Weboplata::ERR_NO_PHONE_MESSAGE, Weboplata::ERR_NO_PHONE);
    } else {
      throw new Exception(Weboplata::ERR_NO_ACCOUNT_MESSAGE, Weboplata::ERR_NO_ACCOUNT);
    }
  }

  /**
   * PaymentAddFromCSV
   *
   *
   * USE [billing35]
   * GO
   *
   * DECLARE @d datetime = getdate()
   *
   * EXEC  [dbo].[PaymentAddfromCSV]
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
    //сумма
    $PaymentSumm = (float)$PaymentSumm;
    //дата
    if (preg_match('/^\d+$/', $PaymentDate)) {
      $tmp = new \DateTime();
      $tmp->setTimestamp($PaymentDate);
      $PaymentDate = $tmp;
      \Logger::getRootLogger()->debug("PaymentDate->" . $PaymentDate->format(\DateTime::ISO8601));
    } else {
      $PaymentDate = \DateTime::createFromFormat('Y-m-d\TH:i:s', $PaymentDate);
    }
    $ExternalPaymentID = (string)$ExternalPaymentID;

    /*
    http://217.114.191.114:9443/hydra/main/?
    command=pay&txn_id=1234567890&txn_date=20180416123744&
    account=1001&sum=12.34&bank_code=Сбербанк&to_account=Сбербанк
    */
    $client = new \Zend_Rest_Client("http://217.114.191.114:9443/hydra/main/");

    $result= $client
      ->command("pay")
      ->txn_id($ExternalPaymentID)
      ->txn_date($PaymentDate->format("YmdHis"))
      ->account($AccountNumber)
      ->sum($PaymentSumm)
      ->bank_code($PSID)
      ->to_account($PSID)
      ->get();

    $RESULT= (int)((string)$result->response->result);

    switch ($RESULT) {
      case self::OK:
        return self::OK;
      case self::ERR_NO_ACCOUNT:
        throw new Exception(self::ERR_NO_ACCOUNT_MESSAGE, $RESULT);
      case self::ERR_REPEAT_EXTERNAL_PAYMENT_ID:
        throw new Exception(self::ERR_REPEAT_EXTERNAL_PAYMENT_ID_MESSAGE, $RESULT);
      default:
        throw new Exception(self::ERR_MESSAGE . ": ".$RESULT);
    }
  }
}