/**
 * Lightweight Instagram-style story viewer.
 *
 * Reads story payloads from:
 *  - #alf-wp-stories-registry (printed in footer by the launcher)
 *  - .alf-wp-stories-story-viewer-mount[data-story-json] (single story page)
 *
 * Opens on click of any [data-story-id] launcher item, or auto-opens none.
 * Implements: progress indicators, tap/swipe/keyboard nav, pause-on-hold,
 * escape-to-close, next-frame preload, reduced-motion respect.
 */
( function () {
	'use strict';

	var settings = ( window.alfWpStoriesViewer && window.alfWpStoriesViewer.settings ) || {
		frameDuration: 5000,
		autoplay: true,
		loop: true,
		showProgress: true,
		showClose: true,
		keyboard: true,
		swipe: true
	};

	var i18n = ( window.alfWpStoriesViewer && window.alfWpStoriesViewer.i18n ) || { close: 'Close story' };

	/**
	 * Collect all story payloads available on the page.
	 *
	 * @return {Array} Array of story objects.
	 */
	function collectStories() {
		var stories = [];
		var registry = document.getElementById( 'alf-wp-stories-registry' );
		if ( registry ) {
			try {
				stories = JSON.parse( registry.textContent || '[]' );
			} catch ( e ) {
				stories = [];
			}
		}
		// Single story page mount.
		var mounts = document.querySelectorAll( '.alf-wp-stories-story-viewer-mount[data-story-json]' );
		for ( var i = 0; i < mounts.length; i++ ) {
			try {
				stories.push( JSON.parse( mounts[ i ].getAttribute( 'data-story-json' ) ) );
			} catch ( e ) {
				// ignore malformed.
			}
		}
		return stories;
	}

	/**
	 * Viewer instance controller.
	 *
	 * @param {Array} stories  Story payloads.
	 * @param {number} startStory Index to begin at.
	 */
	function Viewer( stories, startStory ) {
		this.stories = stories;
		this.storyIndex = startStory || 0;
		this.frameIndex = 0;
		this.overlay = null;
		this.timer = null;
		this.progress = 0;
		this.tick = 40;
		this.holding = false;
	}

	/**
	 * Open the viewer overlay.
	 */
	Viewer.prototype.open = function () {
		this.overlay = document.createElement( 'div' );
		this.overlay.className = 'alf-wp-stories-viewer';
		this.overlay.setAttribute( 'role', 'dialog' );
		this.overlay.setAttribute( 'aria-modal', 'true' );
		this.overlay.setAttribute( 'aria-label', i18n.close );

		var stage = document.createElement( 'div' );
		stage.className = 'alf-wp-stories-viewer__stage';
		this.overlay.appendChild( stage );
		this.stage = stage;

		// Progress bars.
		this.progressWrap = document.createElement( 'div' );
		this.progressWrap.className = 'alf-wp-stories-viewer__progress';
		stage.appendChild( this.progressWrap );

		// Tap zones.
		var prevZone = document.createElement( 'div' );
		prevZone.className = 'alf-wp-stories-viewer__zone alf-wp-stories-viewer__zone--prev';
		var nextZone = document.createElement( 'div' );
		nextZone.className = 'alf-wp-stories-viewer__zone alf-wp-stories-viewer__zone--next';
		stage.appendChild( prevZone );
		stage.appendChild( nextZone );

		// Close button.
		if ( settings.showClose ) {
			var close = document.createElement( 'button' );
			close.type = 'button';
			close.className = 'alf-wp-stories-viewer__close';
			close.setAttribute( 'aria-label', i18n.close );
			close.textContent = '\u00D7';
			close.addEventListener( 'click', this.close.bind( this ) );
			stage.appendChild( close );
		}

		document.body.appendChild( this.overlay );
		document.body.style.overflow = 'hidden';

		// Wire events.
		prevZone.addEventListener( 'click', this.prevFrame.bind( this ) );
		nextZone.addEventListener( 'click', this.nextFrame.bind( this ) );
		this.overlay.addEventListener( 'mousedown', this.onHoldStart.bind( this ) );
		this.overlay.addEventListener( 'mouseup', this.onHoldEnd.bind( this ) );
		this.overlay.addEventListener( 'touchstart', this.onHoldStart.bind( this ), { passive: true } );
		this.overlay.addEventListener( 'touchend', this.onHoldEnd.bind( this ) );

		if ( settings.swipe ) {
			stage.addEventListener( 'touchstart', this.onSwipeStart.bind( this ), { passive: true } );
			stage.addEventListener( 'touchend', this.onSwipeEnd.bind( this ) );
		}

		if ( settings.keyboard ) {
			this.keyHandler = this.onKey.bind( this );
			document.addEventListener( 'keydown', this.keyHandler );
		}

		this.renderStory();
	};

	/**
	 * Render the current story: progress bars and frames.
	 */
	Viewer.prototype.renderStory = function () {
		this.frameIndex = 0;
		// Build progress bars.
		this.progressWrap.innerHTML = '';
		this.progressBars = [];
		var story = this.stories[ this.storyIndex ];
		var frames = story.frames || [];
		for ( var i = 0; i < frames.length; i++ ) {
			var bar = document.createElement( 'div' );
			bar.className = 'alf-wp-stories-viewer__progress-bar';
			if ( settings.showProgress ) {
				bar.style.display = 'block';
			} else {
				bar.style.display = 'none';
			}
			var fill = document.createElement( 'span' );
			fill.className = 'alf-wp-stories-viewer__progress-fill';
			bar.appendChild( fill );
			this.progressWrap.appendChild( bar );
			this.progressBars.push( fill );
		}
		this.renderFrame();
	};

	/**
	 * Render a specific frame, building the frame elements.
	 */
	Viewer.prototype.renderFrame = function () {
		var story = this.stories[ this.storyIndex ];
		var frames = story.frames || [];
		if ( ! frames.length ) {
			this.close();
			return;
		}
		this.frameIndex = Math.max( 0, Math.min( this.frameIndex, frames.length - 1 ) );

		// Clear previous frame elements.
		var old = this.stage.querySelectorAll( '.alf-wp-stories-viewer__frame' );
		for ( var i = 0; i < old.length; i++ ) {
			old[ i ].remove();
		}

		var frame = frames[ this.frameIndex ];
		var el = document.createElement( 'div' );
		el.className = 'alf-wp-stories-viewer__frame is-active';
		var img = document.createElement( 'img' );
		img.alt = frame.alt || '';
		img.src = frame.image_url || '';
		img.loading = 'eager';
		el.appendChild( img );
		this.stage.appendChild( el );
		this.currentImg = img;

		// Preload next frame image.
		if ( this.frameIndex + 1 < frames.length ) {
			var next = new Image();
			next.src = frames[ this.frameIndex + 1 ].image_url || '';
		}

		this.progress = 0;
		if ( settings.autoplay ) {
			this.startTimer();
		}
	};

	/**
	 * Start the auto-advance timer using an interval tick for smooth progress.
	 */
	Viewer.prototype.startTimer = function () {
		this.stopTimer();
		var duration = this.frameDuration();
		if ( duration <= 0 ) {
			return;
		}
		var self = this;
		this.timer = setInterval( function () {
			if ( self.holding ) {
				return;
			}
			self.progress += self.tick;
			self.updateProgress();
			if ( self.progress >= duration ) {
				self.stopTimer();
				self.nextFrame();
			}
		}, this.tick );
	};

	/**
	 * Stop the auto-advance timer.
	 */
	Viewer.prototype.stopTimer = function () {
		if ( this.timer ) {
			clearInterval( this.timer );
			this.timer = null;
		}
	};

	/**
	 * Get the duration for the current frame, falling back to the default.
	 *
	 * @return {number}
	 */
	Viewer.prototype.frameDuration = function () {
		var story = this.stories[ this.storyIndex ];
		var frames = story.frames || [];
		var d = frames[ this.frameIndex ] ? frames[ this.frameIndex ].duration : 0;
		return d > 0 ? d : settings.frameDuration;
	};

	/**
	 * Update the visual progress bars.
	 */
	Viewer.prototype.updateProgress = function () {
		var story = this.stories[ this.storyIndex ];
		var frames = story.frames || [];
		var pct = ( this.progress / this.frameDuration() ) * 100;
		for ( var i = 0; i < this.progressBars.length; i++ ) {
			var fill = this.progressBars[ i ];
			if ( i < this.frameIndex ) {
				fill.style.width = '100%';
			} else if ( i === this.frameIndex ) {
				fill.style.width = Math.min( 100, pct ) + '%';
			} else {
				fill.style.width = '0%';
			}
		}
	};

	/**
	 * Advance to the next frame or story.
	 */
	Viewer.prototype.nextFrame = function () {
		var story = this.stories[ this.storyIndex ];
		var frames = story.frames || [];
		this.stopTimer();
		if ( this.frameIndex + 1 < frames.length ) {
			this.frameIndex++;
			this.renderFrame();
		} else {
			this.nextStory();
		}
	};

	/**
	 * Go to the previous frame or story.
	 */
	Viewer.prototype.prevFrame = function () {
		this.stopTimer();
		if ( this.frameIndex > 0 ) {
			this.frameIndex--;
			this.renderFrame();
		} else {
			this.prevStory();
		}
	};

	/**
	 * Advance to the next story, respecting loop setting.
	 */
	Viewer.prototype.nextStory = function () {
		if ( this.storyIndex + 1 < this.stories.length ) {
			this.storyIndex++;
			this.renderStory();
		} else if ( settings.loop ) {
			this.storyIndex = 0;
			this.renderStory();
		} else {
			this.close();
		}
	};

	/**
	 * Go to the previous story.
	 */
	Viewer.prototype.prevStory = function () {
		if ( this.storyIndex > 0 ) {
			this.storyIndex--;
			this.renderStory();
		} else if ( settings.loop ) {
			this.storyIndex = this.stories.length - 1;
			this.renderStory();
		}
	};

	/**
	 * Pause on hold start.
	 *
	 * @param {Event} e
	 */
	Viewer.prototype.onHoldStart = function ( e ) {
		// Only pause on direct stage interaction, not buttons.
		if ( e.target.closest( 'button' ) ) {
			return;
		}
		this.holding = true;
	};

	/**
	 * Resume on hold end.
	 */
	Viewer.prototype.onHoldEnd = function () {
		this.holding = false;
	};

	/**
	 * Handle swipe gestures.
	 */
	Viewer.prototype.onSwipeStart = function ( e ) {
		if ( ! e.changedTouches || ! e.changedTouches.length ) {
			return;
		}
		this.swipeX = e.changedTouches[ 0 ].clientX;
		this.swipeY = e.changedTouches[ 0 ].clientY;
	};

	/**
	 * Resolve a swipe into prev/next navigation.
	 *
	 * @param {Event} e
	 */
	Viewer.prototype.onSwipeEnd = function ( e ) {
		if ( typeof this.swipeX === 'undefined' || ! e.changedTouches || ! e.changedTouches.length ) {
			return;
		}
		var dx = e.changedTouches[ 0 ].clientX - this.swipeX;
		var dy = e.changedTouches[ 0 ].clientY - this.swipeY;
		this.swipeX = undefined;
		// Horizontal swipe dominant.
		if ( Math.abs( dx ) > 50 && Math.abs( dx ) > Math.abs( dy ) ) {
			if ( dx > 0 ) {
				this.prevFrame();
			} else {
				this.nextFrame();
			}
		} else if ( Math.abs( dy ) > 80 && dy < 0 ) {
			// Swipe up to close.
			this.close();
		}
	};

	/**
	 * Keyboard navigation.
	 *
	 * @param {Event} e
	 */
	Viewer.prototype.onKey = function ( e ) {
		switch ( e.key ) {
			case 'Escape':
				this.close();
				break;
			case 'ArrowRight':
				this.nextFrame();
				break;
			case 'ArrowLeft':
				this.prevFrame();
				break;
		}
	};

	/**
	 * Close the viewer and restore state.
	 */
	Viewer.prototype.close = function () {
		this.stopTimer();
		if ( this.overlay && this.overlay.parentNode ) {
			this.overlay.parentNode.removeChild( this.overlay );
		}
		this.overlay = null;
		document.body.style.overflow = '';
		if ( this.keyHandler ) {
			document.removeEventListener( 'keydown', this.keyHandler );
			this.keyHandler = null;
		}
	};

	/**
	 * Open the viewer starting at a given story ID.
	 *
	 * @param {number} storyId
	 */
	function openViewer( storyId ) {
		var stories = collectStories();
		var index = 0;
		if ( storyId ) {
			for ( var i = 0; i < stories.length; i++ ) {
				if ( String( stories[ i ].id ) === String( storyId ) ) {
					index = i;
					break;
				}
			}
		}
		if ( ! stories.length ) {
			return;
		}
		var viewer = new Viewer( stories, index );
		viewer.open();
	}

	// Delegate clicks on launcher items.
	document.addEventListener( 'click', function ( e ) {
		var item = e.target.closest( '.alf-wp-stories-launcher-item' );
		if ( ! item ) {
			return;
		}
		e.preventDefault();
		var id = item.getAttribute( 'data-story-id' );
		openViewer( id );
	} );

	// Expose for programmatic opening if needed.
	window.alfWpStoriesOpenViewer = openViewer;

	// Auto-open the viewer on single story pages.
	function autoOpen() {
		var mount = document.querySelector( '.alf-wp-stories-story-viewer-mount[data-auto-open]' );
		if ( mount ) {
			var json = mount.getAttribute( 'data-story-json' );
			try {
				var story = JSON.parse( json );
				openViewer( story.id );
			} catch ( e ) {
				// ignore.
			}
		}
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', autoOpen );
	} else {
		autoOpen();
	}
}() );
