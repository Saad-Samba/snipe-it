<?php

namespace App\Presenters;

/**
 * Class CategoryPresenter
 */
class CategoryPresenter extends Presenter
{
    /**
     * Json Column Layout for bootstrap table
     * @return string
     */
    public static function dataTableLayout()
    {
        $layout = [
            [
                'field'        => 'checkbox',
                'checkbox'     => true,
                'titleTooltip' => trans('general.select_all_none'),
            ],
            [
                'field' => 'id',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.id'),
                'visible' => false,
            ], [
                'field' => 'name',
                'searchable' => true,
                'sortable' => true,
                'switchable' => false,
                'title' => trans('general.name'),
                'visible' => true,
                'formatter' => 'categoriesLinkFormatter',
            ], [
                'field' => 'image',
                'searchable' => false,
                'sortable' => true,
                'title' => trans('general.image'),
                'visible' => true,
                'formatter' => 'imageFormatter',
            ], [
                'field' => 'category_type',
                'searchable' => true,
                'sortable' => true,
                'title' => trans('general.type'),
                'visible' => true,
            ], [
                'field' => 'manager',
                'searchable' => false,
                'sortable' => true,
                'title' => 'Category Manager',
                'visible' => true,
                'formatter' => 'usersLinkObjFormatter',
            ], [
                'field' => 'item_count',
                'searchable' => false,
                'sortable' => true,
                'title' => trans('general.qty'),
                'visible' => true,
            ], [
                'field' => 'available_models_count',
                'searchable' => false,
                'sortable' => true,
                'title' => 'Reusable Inventory <span class="label label-info" data-tooltip="true" title="Recently released feature">NEW</span>',
                'visible' => true,
                'formatter' => 'categoryReusableInventoryFormatter',
            ], [
                'field' => 'tag_color',
                'searchable' => true,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.tag_color'),
                'visible' => false,
                'formatter' => 'colorTagFormatter',
            ], [
                'field' => 'notes',
                'searchable' => true,
                'sortable' => true,
                'visible' => false,
                'title' => trans('general.notes'),
            ], [
                'field' => 'created_by',
                'searchable' => false,
                'sortable' => true,
                'title' => trans('general.created_by'),
                'visible' => false,
                'formatter' => 'usersLinkObjFormatter',
            ], [
                'field' => 'created_at',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.created_at'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ], [
                'field' => 'updated_at',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.updated_at'),
                'visible' => false,
                'formatter' => 'dateDisplayFormatter',
            ], [
                'field' => 'actions',
                'searchable' => false,
                'sortable' => false,
                'switchable' => false,
                'title' => trans('table.actions'),
		        'formatter' => 'categoriesActionsFormatter',
                'printIgnore' => true,
            ],
        ];

        return json_encode($layout);
    }

    /**
     * Link to this categories name
     * @return string
     */
    public function nameUrl()
    {
        return (string) link_to_route('categories.show', $this->name, $this->id);
    }

    /**
     * Url to view this item.
     * @return string
     */
    public function viewUrl()
    {
        return route('categories.show', $this->id);
    }

    public function formattedNameLink() {

        if (auth()->user()->can('view', ['\App\Models\Category', $this])) {
            return ($this->tag_color ? "<i class='fa-solid fa-fw fa-square' style='color: ".e($this->tag_color)."' aria-hidden='true'></i>" : '').'<a href="'.route('categories.show', e($this->id)).'">'.e($this->name).'</a>';
        }

        return ($this->tag_color ? "<i class='fa-solid fa-fw fa-square' style='color: ".e($this->tag_color)."' aria-hidden='true'></i> " : '').$this->name;
    }
}
