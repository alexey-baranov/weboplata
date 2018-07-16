<?php
/**
 * Created by PhpStorm.
 * User: alexey_baranov
 * Date: 11.10.14
 * Time: 17:49
 *
 * Чисто для Сбербанка
 */

namespace Weboplata;


use Billing\Core;
use Billing\Domain\Account;
use Billing\Domain\Subscriber;

/**
 * sbrf - сбербанк онлайн
 */
class Weboplata2
{
  const OK = 0;
  const ERR_NO_ACCOUNT = 5;
  const ERR_NO_PHONE = 5;
  const ERR = 300;
  const ERR_REPEAT_EXTERNAL_PAYMENT_ID = 8;

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
  function getAccountByPhoneNumber($phoneNumber)
  {
    \Logger::getRootLogger()->debug("looking Account by phone number ".$phoneNumber);
    $st = Core::getEm()->getConnection()->prepare("
            select a.ID
            
            from
            billing35.dbo.Account a
            join billing35.dbo.ServiceAccount sa  on a.id = sa.AccountID
            join billing35.dbo.ATSAccount ata on sa.id = ata.id
            
            where
            Port = :phoneNumber
            ");
    $st->bindValue(":phoneNumber", $phoneNumber);
    $st->execute();

    $RESULT = $st->fetchColumn();

    if ($RESULT === false) {
      throw new Exception(self::ERR_NO_PHONE_MESSAGE, self::ERR_NO_PHONE);
    }
    /** @var  $result Account*/
    $result= Core::getEm()->find('Billing\Domain\Account', $RESULT);

    \Logger::getRootLogger()->debug($phoneNumber." -> ".$result->getNumber(). " ". $result->getSubscriber()->getName());

    return $result;
  }

  /**
   * Чисто для юниттестов тестилка
   *
   * @param $phoneNumber
   * @return Account
   * @throws Exception
   */
  function getAccountNumberByPhoneNumber($phoneNumber)
  {
    return $this->getAccountByPhoneNumber($phoneNumber)->getNumber();
  }

  /**
   * Проверяет наличие счета или телефона
   * http://localhost/weboplata/public/2.php?method=check&account=789-%D0%98&txn_id=12345
   *
   * @param string $account номер счета / или телефона !без тире!
   *
   * @throws Exception неверный номер договора
   * @return \SimpleXMLElement
   */
  function check($account)
  {
    try {
      \Logger::getLogger("Weboplata2")->info("checking ". $account);

      $serviceType;

      if (preg_match('/-.+$/', $account)) {
        $serviceType = 1;
      } else {
        $serviceType = 7;
        $account = $this->getAccountByPhoneNumber($account)->getNumber();
      }

      $st = Core::getEm()->getConnection()->prepare("
          SELECT TOP 1
            a.Number AS number,
            li.LegalAddress AS address,
            tpn.Name AS tariff,
            a.Saldo AS saldo,
            s.IsLegal
          
          FROM Subscriber s
            LEFT JOIN legalInfo li ON li.SubscriberID=s.ID
            JOIN Account a ON a.SubscriberID= s.ID
            JOIN ServiceAccount sa ON sa.AccountID=a.ID
            JOIN TariffPlanName tpn ON tpn.ID = sa.TariffPlanNameID
          
          WHERE
            a.Number = :AccountNumber
            AND (
                a.Number LIKE '%-И' AND sa.ServiceAccountType=3
                OR a.Number LIKE '%-Т' AND sa.ServiceAccountType=101
            )
            AND (
              s.IsLegal = 0
              OR s.IsLegal= 1 AND a.Number LIKE 'А%'
              OR a.Number LIKE '555999%'
            )
");
      $st->bindParam(":AccountNumber", $account);

      $st->execute();

      //на винде fetch почему-то не выполняется в случае добавления платежа. выдает ошибку "The active result for the query contains no fields"
      $metadataAsArray = $st->fetch();


      if ($metadataAsArray) {
        \Logger::getLogger("Weboplata2")->info($metadataAsArray);
        $result = new \SimpleXMLElement("<?xml version='1.0' encoding='utf-8'?>
            <response>
              <result>" . self::OK . "</result>
              <tariff>{$metadataAsArray["tariff"]}</tariff>
              <saldo>{$metadataAsArray["saldo"]}</saldo>
            </response>
        ");
        return $result;
      } else if ($serviceType == 7) {
        throw new Exception(self::ERR_NO_PHONE_MESSAGE, self::ERR_NO_PHONE);
      } else {
        throw new Exception(self::ERR_NO_ACCOUNT_MESSAGE, self::ERR_NO_ACCOUNT);
      }
    }
    catch(\Exception $ex){
      \Logger::getLogger("Weboplata2")->error($ex);
      $result = new \SimpleXMLElement("<?xml version='1.0' encoding='utf-8'?>
            <response>
              <result>" . ($ex->getCode()?:self::ERR) . "</result>
            </response>
        ");
      return $result;
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
   * @param string $account номер счета / или телефона !без тире!
   * @param string $sum сумма
   * @param string $txn_date дата платежа
   * @param string $txn_id номер платежа во внешней системе
   *
   * @throws \Exception 2-нет счета, 4-повторный $ExternalPaymentID, 5-др. ошибка
   *
   * @return \SimpleXMLElement 0-ОК
   *
   */
  function pay($account, $sum, $txn_date, $txn_id)
  {
    try {
      \Logger::getLogger("Weboplata2")->info("paying " . $account, $sum, $txn_date, $txn_id);

      $serviceType;

      if (preg_match('/-.+$/', $account)) {
        $serviceType = 1;
      } else {
        $serviceType = 7;
        $account = $this->getAccountByPhoneNumber($account)->getNumber();
      }
      //сумма
      $sum = (float)$sum;
      //дата
      $txn_date = \DateTime::createFromFormat('YmdHis', $txn_date);

      $txn_id = (string)$txn_id;
      $CurrencyCode = 810;
      //по служебке №123490 сделал числом, а до этого был null
      $PaymentNumber = $txn_date->format("Hi");
      //тип платежа
      $PaymentType = 3;
      //платежная система
      $PSID = "sbrf2";
      if (isset($_ENV["PHP_ENV"])) {
        $PSID = $PSID . "-" . $_ENV["PHP_ENV"];
      }

      $st = Core::getEm()->getConnection()->prepare("exec PaymentAddFromCSV :AccountNumber, :PaymentSumm, :PaymentDate, :PSID, :CurrencyCode, :txn_id, :ExternalPaymentID,:PaymentNumber, :PaymentType");
      $Result = null;
//            $st->bindParam(":Result", $Result, \PDO::PARAM_INT, \PDO::PARAM_INPUT_OUTPUT);
      $st->bindParam(":AccountNumber", $account);
      $st->bindParam(":PaymentSumm", $sum);
      $st->bindParam(":PaymentDate", $txn_date->format("Y-m-d H:i:s"));
//            $st->bindParam(":PaymentDate", $PaymentDate="2014-10-11T20:00:00");
      $st->bindParam(":PSID", $PSID);
      $st->bindParam(":CurrencyCode", $CurrencyCode);
      $st->bindParam(":txn_id", $txn_id);
      $st->bindParam(":ExternalPaymentID", $txn_id);
      $st->bindParam(":PaymentNumber", $PaymentNumber);
      $st->bindParam(":PaymentType", $PaymentType);


      $st->execute();

      //на винде fetch почему-то не выполняется в случае добавления платежа. выдает ошибку "The active result for the query contains no fields"
      $result = $st->fetchColumn();

      switch ($result) {
        case self::OK:
          \Logger::getLogger("Weboplata2")->info("pay done");
          $result = new \SimpleXMLElement("<?xml version='1.0' encoding='utf-8'?>
            <response>
              <osmp_txn_id>{$txn_id}</osmp_txn_id>
              <sum>{$sum}</sum>
              <result>0</result>
            </response>
        ");
          return $result;
          break;
        case 2:
          if ($serviceType == 7) {
            throw new Exception(self::ERR_NO_PHONE_MESSAGE, self::ERR_NO_PHONE);
          } else {
            throw new Exception(self::ERR_NO_ACCOUNT_MESSAGE, self::ERR_NO_ACCOUNT);
          }
        case 4:
          throw new Exception(self::ERR_REPEAT_EXTERNAL_PAYMENT_ID_MESSAGE, self::ERR_REPEAT_EXTERNAL_PAYMENT_ID);
        default:
          throw new Exception(self::ERR_MESSAGE . ": [{$result}]", self::ERR);
      }
    }
    catch(\Exception $ex){
      \Logger::getLogger("Weboplata2")->error($ex);
      $result = new \SimpleXMLElement("<?xml version='1.0' encoding='utf-8'?>
            <response>
              <result>" . ($ex->getCode()?:self::ERR) . "</result>
            </response>
        ");
      return $result;

    }
  }
}