<?php namespace Bkwld\Cloner;

// Deps
use App;

/**
 * Mixin accessor methods, callbacks, and the duplicate() helper into models.
 */
trait Cloneable {

	/**
	 * Return the list of attributes on this model that should be cloned
	 *
	 * @return  array
	 */
	public function getCloneExemptAttributes() {

		// Alwyas make the id and timestamps exempt
		$defaults = [
			$this->getKeyName(),
			$this->getCreatedAtColumn(),
			$this->getUpdatedAtColumn(),
		];

		// It none specified, just return the defaults, else, merge them
		if (!isset($this->clone_exempt_attributes)) return $defaults;
		return array_merge($defaults, $this->clone_exempt_attributes);
	}

	/**
	 * Return a list of attributes that reference files that should be duplicated
	 * when the model is cloned
	 *
	 * @return  array
	 */
	public function getCloneableFileAttributes() {
		if (!isset($this->cloneable_file_attributes)) return [];
		return $this->cloneable_file_attributes;
	}

	/**
	 * Return the list of relations on this model that should be cloned
	 *
	 * @return  array
	 */
	public function getCloneableRelations() {
		if (!isset($this->cloneable_relations)) return [];
		return $this->cloneable_relations;
	}

	/**
	 * Return the list of pivot data for the relations
	 *
	 * @return  array
	 */
	public function getCloneableRelationsPivotData() {
		if (!isset($this->cloneable_relations_pivot_data)) return [];
		return $this->cloneable_relations_pivot_data;
	}

	/**
	 * Return the list of pivot data for the relation passed as parameter
	 *
	 * @return  array
	 */
	public function getCloneableRelationPivotData($relation) {

		$pivot_data_array = $this->getCloneableRelationsPivotData();
		$pivot_data = collect($pivot_data_array)->get($relation);
		return ($pivot_data === null)?[]:$pivot_data;
	}

	/**
	 * Add a relation to cloneable_relations uniquely
	 *
	 * @param  string $relation
	 * @return void
	 */
	public function addCloneableRelation($relation) {
		$relations = $this->getCloneableRelations();
		if (in_array($relation, $relations)) return;
		$relations[] = $relation;
		$this->cloneable_relations = $relations;
	}

	/**
	 * Clone the current model instance
	 *
	 * @return Illuminate\Database\Eloquent\Model The new, saved clone
	 */
	public function duplicate() {
		return App::make('cloner')->duplicate($this);
	}

	/**
	 * Clone the current model instance to a specific Laravel database connection
	 *
	 * @param  string $connection A Laravel database connection
	 * @return Illuminate\Database\Eloquent\Model The new, saved clone
	 */
	public function duplicateTo($connection) {
		return App::make('cloner')->duplicateTo($this, $connection);
	}

	/**
	 * A no-op callback that gets fired when a model is cloning but before it gets
	 * committed to the database
	 *
	 * @param  Illuminate\Database\Eloquent\Model $src
	 * @return void
	 */
	public function onCloning($src) {}

	/**
	 * A no-op callback that gets fired when a model is cloned and saved to the
	 * database
	 *
	 * @param  Illuminate\Database\Eloquent\Model $src
	 * @return void
	 */
	public function onCloned($src) {}

}
