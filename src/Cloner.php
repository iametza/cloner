<?php namespace Bkwld\Cloner;

// Deps
use Illuminate\Events\Dispatcher as Events;

/**
 * Core class that traverses a model's relationships and replicates model
 * attributes
 */
class Cloner {

	/**
	 * @var AttachmentAdapter
	 */
	private $attachment;

	/**
	 * @var Events
	 */
	private $events;

	/**
	 * @var string
	 */
	private $write_connection;

	/**
	 * DI
	 *
	 * @param AttachmentAdapter $attachment
	 */
	public function __construct(AttachmentAdapter $attachment = null,
		Events $events = null) {
		$this->attachment = $attachment;
		$this->events = $events;
	}

	/**
	 * Clone a model instance and all of it's files and relations
	 *
	 * @param  Illuminate\Database\Eloquent\Model $model
	 * @param  Illuminate\Database\Eloquent\Relations\Relation $relation
	 * @return Illuminate\Database\Eloquent\Model The new model instance
	 */
	public function duplicate($model, $relation = null) {
		$clone = $this->cloneModel($model);
		$this->duplicateAttachments($clone);
		$this->saveClone($clone, $relation, $model);
		$this->cloneRelations($model, $clone);
		return $clone;
	}

	/**
	 * Clone a model instance to a specific database connection
	 *
	 * @param  Illuminate\Database\Eloquent\Model $model
	 * @param  string $connection A Laravel database connection
	 * @return Illuminate\Database\Eloquent\Model The new model instance
	 */
	public function duplicateTo($model, $connection) {
		$this->write_connection = $connection; // Store the write database connection
		$clone = $this->duplicate($model); // Do a normal duplicate
		$this->write_connection = null; // Null out the connection for next run
		return $clone;
	}

	/**
	 * Create duplicate of the model
	 *
	 * @param  Illuminate\Database\Eloquent\Model $model
	 * @return Illuminate\Database\Eloquent\Model The new model instance
	 */
	protected function cloneModel($model) {
		$exempt = method_exists($model, 'getCloneExemptAttributes') ?
			$model->getCloneExemptAttributes() : null;
		$clone = $model->replicate($exempt);
		if ($this->write_connection) $clone->setConnection($this->write_connection);
		return $clone;
	}

	/**
	 * Duplicate all attachments, given them a new name, and update the attribute
	 * value
	 *
	 * @param  Illuminate\Database\Eloquent\Model $clone
	 * @return void
	 */
	protected function duplicateAttachments($clone) {
		if (!$this->attachment || !method_exists($clone, 'getCloneableFileAttributes')) return;
		foreach($clone->getCloneableFileAttributes() as $attribute) {
			if (!$original = $clone->getAttribute($attribute)) continue;
			$clone->setAttribute($attribute, $this->attachment->duplicate($original));
		}
	}

	/**
	 * Save the clone. If a relation was passed, save the clone onto that
	 * relation.  Otherwise, just save it.
	 *
	 * @param  Illuminate\Database\Eloquent\Model $clone
	 * @param  Illuminate\Database\Eloquent\Relations\Relation $relation
	 * @param  Illuminate\Database\Eloquent\Model $src The orginal model
	 * @return void
	 */
	protected function saveClone($clone, $relation = null, $src) {

		// Notify listeners via callback or event
		if (method_exists($clone, 'onCloning')) $clone->onCloning($src);
		$this->events->fire('cloner::cloning: '.get_class($src), [$clone, $src]);

		// Do the save
		if ($relation) $relation->save($clone);
		else $clone->save();

		// Notify listeners via callback or event
		if (method_exists($clone, 'onCloned')) $clone->onCloned($src);
		$this->events->fire('cloner::cloned: '.get_class($src), [$clone, $src]);
	}

	/**
	 * Loop through relations and clone or re-attach them
	 *
	 * @param  Illuminate\Database\Eloquent\Model $model
	 * @param  Illuminate\Database\Eloquent\Model $clone
	 * @return void
	 */
	protected function cloneRelations($model, $clone) {
		if (!method_exists($model, 'getCloneableRelations')) return;
		foreach($model->getCloneableRelations() as $relation_name => $pivot_data) {
			$this->duplicateRelation($model, $relation_name, $pivot_data, $clone);
		}
	}

	/**
	 * Duplicate relationships to the clone
	 *
	 * @param  Illuminate\Database\Eloquent\Model $model
	 * @param  string $relation_name
	 * @param  array $pivot_data
	 * @param  Illuminate\Database\Eloquent\Model $clone
	 * @return void
	 */
	protected function duplicateRelation($model, $relation_name, $pivot_data, $clone) {
 		$relation = call_user_func([$model, $relation_name]);
 		if (is_a($relation, 'Illuminate\Database\Eloquent\Relations\BelongsToMany')) {
 			$this->duplicatePivotedRelation($relation, $relation_name, $pivot_data, $clone);
 		} else $this->duplicateDirectRelation($relation, $relation_name, $clone);
 	}

	/**
	 * Duplicate a many-to-many style relation where we are just attaching the
	 * relation to the dupe
	 *
	 * @param  Illuminate\Database\Eloquent\Relations\Relation $relation
	 * @param  string $relation_name
	 * @param  array $pivot_data
	 * @param  Illuminate\Database\Eloquent\Model $clone
	 * @return void
	 */
	protected function duplicatePivotedRelation($relation, $relation_name, $pivot_data, $clone) {

		// If duplicating between databases, do not duplicate relations. The related
		// instance may not exist in the other database or could have a different
		// primary key.
		if ($this->write_connection) return;

		// Loop trough current relations and attach to clone
		$relation->get()->each(function($foreign) use ($clone, $relation_name, $pivot_data) {
			$clone->$relation_name()->attach($foreign, $pivot_data);
		});
	}

	/**
	 * Duplicate a one-to-many style relation where the foreign model is ALSO
	 * cloned and then associated
	 *
	 * @param  Illuminate\Database\Eloquent\Relations\Relation $relation
	 * @param  string $relation_name
	 * @param  Illuminate\Database\Eloquent\Model $clone
	 * @return void
	 */
	protected function duplicateDirectRelation($relation, $relation_name, $clone) {
		$relation->get()->each(function($foreign) use ($clone, $relation_name) {
			$this->duplicate($foreign, $clone->$relation_name());
		});
	}
}
