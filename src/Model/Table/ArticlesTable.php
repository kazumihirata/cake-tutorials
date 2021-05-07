<?php

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\Validation\Validator;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use Cake\Utility\Text;

class ArticlesTable extends Table
{
    /**
     * @param array $config
     */
    public function initialize(array $config): void
    {
        $this->addBehavior('Timestamp');
        $this->belongsToMany('Tags');
    }

    /**
     * @param EventInterface $event
     * @param $entity
     * @param $options
     */
    public function beforeSave(EventInterface $event, $entity, $options)
    {
        if ($entity->tag_string) {
            $entity->tags = $this->_buildTags($entity->tag_string);
        }

        if ($entity->isNew() && !$entity->slug) {
            $sluggedTitle = Text::slug($entity->title);
            $entity->slug = substr($sluggedTitle, 0, 191);
        }
    }

    /**
     * @param Validator $validator
     * @return Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('title')
            ->minLength('title', 10)
            ->maxLength('title', 255)

            ->notEmptyString('body')
            ->minLength('body', 10);

        return $validator;
    }

    /**
     * @param Query $query
     * @param array $options
     * @return Query
     */
    public function findTagged(Query $query, array $options)
    {
        $columns = [
            'Articles.id', 'Articles.user_id', 'Articles.title',
            'Articles.body', 'Articles.published', 'Articles.created',
            'Articles.slug',
        ];

        $query = $query->select($columns)->distinct($columns);

        if (empty($options['tags'])) {
            $query->leftJoinWith('Tags')->where(['Tags.title IS' => null]);
        } else {
            $query->innerJoinWith('Tags')->where(['Tags.title IN' => $options['tags']]);
        }

        return $query->group(['Articles.id']);
    }

    /**
     * @param $tagString
     * @return array
     */
    protected function _buildTags($tagString)
    {
        // タグをトリミング
        $newTags = array_map('trim', explode(',', $tagString));
        // 全てのからのタグを削除
        $newTags = array_filter($newTags);
        // 重複するタグの削減
        $newTags = array_unique($newTags);

        $out = [];
        $query = $this->Tags->find()->where(['Tags.title IN' => $newTags]);

        // 新しいタグのリストから既存のタグを削除。
        foreach ($query->extract('title') as $existing) {
            $index = array_search($existing, $newTags);
            if ($index !== false) {
                unset($newTags[$index]);
            }
        }
        // 既存のタグを追加。
        foreach ($query as $tag) {
            $out[] = $tag;
        }
        // 新しいタグを追加。
        foreach ($newTags as $tag) {
            $out[] = $this->Tags->newEntity(['title' => $tag]);
        }

        return $out;
    }
}
