<?php

namespace Crm\ProductsModule\Forms;

use Crm\ProductsModule\Repository\TagsRepository;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Form;
use Nette\Utils\Html;
use Tomaj\Form\Renderer\BootstrapRenderer;

class TagsFormFactory
{
    private $tagsRepository;

    private $translator;

    public $onSave;

    public $onUpdate;

    public function __construct(
        TagsRepository $tagsRepository,
        Translator $translator
    ) {
        $this->tagsRepository = $tagsRepository;
        $this->translator = $translator;
    }

    /**
     * @return Form
     */
    public function create($tagId)
    {
        $defaults = [];
        if (isset($tagId)) {
            $tag = $this->tagsRepository->find($tagId);
            $defaults = $tag->toArray();
            if (isset($defaults['sorting']) && $defaults['sorting'] > 1) {
                $defaults['sorting'] = $defaults['sorting'] - 1; // select element after which current tag is displayed
            } else {
                $defaults['sorting'] = null;
            }
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addText('code', 'products.data.tags.fields.code')
            ->setRequired('products.data.tags.errors.code')
            ->setAttribute('placeholder', 'products.data.tags.placeholder.code');

        $form->addText('icon', 'products.data.tags.fields.icon')
            ->setRequired('products.data.tags.errors.icon')
            ->setOption('description', Html::el('a href="http://fontawesome.io/icons/"', $this->translator->trans('products.data.tags.descriptions.icon')))
            ->setAttribute('placeholder', 'products.data.tags.placeholder.icon');

        $tagPairsQuery = $this->tagsRepository->all()->where('sorting IS NOT NULL');
        if ($tagId) {
            $tagPairsQuery->where('id != ?', $tagId);
        }
        $tagPairs = $tagPairsQuery->fetchPairs('sorting', 'code');
        $form->addSelect('sorting', 'products.data.tags.fields.sorting', $tagPairs)
            ->setPrompt('products.data.tags.placeholder.sorting');

        $form->addCheckbox('visible', 'products.data.tags.fields.visible');

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        if ($tagId) {
            $form->addHidden('id', $tagId);
        }

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    /**
     * @param $form
     * @param $values
     */
    public function formSucceeded($form, $values)
    {
        $values['sorting'] = (int)$values['sorting'] + 1; // sort after the selected element

        if (isset($values['id'])) {
            $tag = $this->tagsRepository->find($values['id']);

            if ($tag->sorting && $values['sorting'] > $tag->sorting) {
                $values['sorting'] = $values['sorting'] - 1;
            }
            $this->tagsRepository->updateSorting($values['sorting'], $tag->sorting);

            $this->tagsRepository->update($tag, $values);
            $this->onUpdate->__invoke($tag);
        } else {
            $tag = $this->tagsRepository->add($values['code'], $values['icon'], $values['visible']);
            $this->onSave->__invoke($tag);
        }
    }
}
