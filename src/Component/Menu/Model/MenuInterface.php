<?php

namespace Pagekit\Component\Menu\Model;

interface MenuInterface
{
    /**
     * @return string
     */
    public function getId();

    /**
     * @param string
     */
    public function setId($id);

    /**
     * Add a new menu item instance.
     *
     * @param  ItemInterface $item
     */
    public function addItem(ItemInterface $item);

    /**
     * Get a menu item instance.
     *
     * @param  string $id
     * @return ItemInterface
     */
    public function getItem($id);

    /**
     * @param param ItemInterface[] $items
     */
    public function setItems(array $items = array());

    /**
     * @return ItemInterface[]
     */
    public function getItems();
}