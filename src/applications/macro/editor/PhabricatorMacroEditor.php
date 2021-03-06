<?php

final class PhabricatorMacroEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorMacroApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Macros');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorMacroTransactionType::TYPE_NAME;
    $types[] = PhabricatorMacroTransactionType::TYPE_DISABLED;
    $types[] = PhabricatorMacroTransactionType::TYPE_FILE;
    $types[] = PhabricatorMacroTransactionType::TYPE_AUDIO;
    $types[] = PhabricatorMacroTransactionType::TYPE_AUDIO_BEHAVIOR;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
        return $object->getName();
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
        return $object->getIsDisabled();
      case PhabricatorMacroTransactionType::TYPE_FILE:
        return $object->getFilePHID();
      case PhabricatorMacroTransactionType::TYPE_AUDIO:
        return $object->getAudioPHID();
      case PhabricatorMacroTransactionType::TYPE_AUDIO_BEHAVIOR:
        return $object->getAudioBehavior();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
      case PhabricatorMacroTransactionType::TYPE_FILE:
      case PhabricatorMacroTransactionType::TYPE_AUDIO:
      case PhabricatorMacroTransactionType::TYPE_AUDIO_BEHAVIOR:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        break;
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
        $object->setIsDisabled($xaction->getNewValue());
        break;
      case PhabricatorMacroTransactionType::TYPE_FILE:
        $object->setFilePHID($xaction->getNewValue());
        break;
      case PhabricatorMacroTransactionType::TYPE_AUDIO:
        $object->setAudioPHID($xaction->getNewValue());
        break;
      case PhabricatorMacroTransactionType::TYPE_AUDIO_BEHAVIOR:
        $object->setAudioBehavior($xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMacroTransactionType::TYPE_FILE:
      case PhabricatorMacroTransactionType::TYPE_AUDIO:
        // When changing a macro's image or audio, attach the underlying files
        // to the macro (and detach the old files).
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();
        $all = array();
        if ($old) {
          $all[] = $old;
        }
        if ($new) {
          $all[] = $new;
        }

        $files = id(new PhabricatorFileQuery())
          ->setViewer($this->requireActor())
          ->withPHIDs($all)
          ->execute();
        $files = mpull($files, null, 'getPHID');

        $old_file = idx($files, $old);
        if ($old_file) {
          $old_file->detachFromObject($object->getPHID());
        }

        $new_file = idx($files, $new);
        if ($new_file) {
          $new_file->attachToObject($object->getPHID());
        }
        break;
    }
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
      case PhabricatorMacroTransactionType::TYPE_NAME:
      case PhabricatorMacroTransactionType::TYPE_DISABLED:
      case PhabricatorMacroTransactionType::TYPE_FILE:
      case PhabricatorMacroTransactionType::TYPE_AUDIO:
      case PhabricatorMacroTransactionType::TYPE_AUDIO_BEHAVIOR:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorMacroTransactionType::TYPE_NAME;
          return ($xaction->getOldValue() !== null);
        default:
          break;
      }
    }
    return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhabricatorMacroReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $name = $object->getName();
    $name = 'Image Macro "'.$name.'"';

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($name)
      ->addHeader('Thread-Topic', $name);
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $this->requireActor()->getPHID(),
    );
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);
    $body->addLinkSection(
      pht('MACRO DETAIL'),
      PhabricatorEnv::getProductionURI('/macro/view/'.$object->getID().'/'));

    return $body;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.macro.subject-prefix');
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }
}
