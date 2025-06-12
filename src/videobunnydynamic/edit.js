/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Placeholder, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './editor.scss';

/**
 * Edit component for the Video Bunny block
 */
export default function Edit({ attributes, setAttributes }) {
    const [videos, setVideos] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const blockProps = useBlockProps();
    const { videoId } = attributes;

    // Fetch videos from Bunny.net on component mount
    useEffect(() => {
        fetchVideos();
    }, []);

    // Fetch videos from the REST API endpoint
    const fetchVideos = async () => {
        try {
            const response = await apiFetch({ path: '/wp/v2/video' });
            const videoOptions = response
                .filter(video => video.meta && video.meta.bunny_video_id)
                .map(video => ({
                    label: video.title.rendered,
                    value: video.meta.bunny_video_id
                }));
            setVideos(videoOptions);
        } catch (error) {
            console.error('Error fetching videos:', error);
        } finally {
            setIsLoading(false);
        }
    };

    // Get settings from WordPress
    const { libraryId, pullZone } = useSelect(select => ({
        libraryId: select(coreStore).getEntityRecord('root', 'site')?.bunny_video_library_id,
        pullZone: select(coreStore).getEntityRecord('root', 'site')?.bunny_video_pull_zone
    }), []);

    // Generate video preview URL
    const getVideoUrl = () => {
       if (!videoId || !libraryId) return '';
        return `${pullZone || `https://video.bunnycdn.com/play/${libraryId}`}/${videoId}`;
    }; 

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Video Settings', 'bunny-video-plugin')}>
                    {isLoading ? (
                        <Spinner />
                    ) : (
                        <SelectControl
                            label={__('Select Video', 'bunny-video-plugin')}
                            value={videoId}
                            options={[
                                { label: __('Select a video...', 'bunny-video-plugin'), value: '' },
                                ...videos
                            ]}
                            onChange={newVideoId => setAttributes({ videoId: newVideoId })}
                        />
                    )}
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                {videoId ? (
                    <iframe
                        src={getVideoUrl()}
                        loading="lazy"
                        type="text/html"
                        width="100%"
                        style={{ aspectRatio: '16/9' }}
                        frameBorder="0"
                        title={__('Bunny.net Video Player', 'bunny-video-plugin')}
                        allowFullScreen
                    />
                ) : (
                    <Placeholder
                        icon="video-alt3"
                        label={__('Bunny Video', 'bunny-video-plugin')}
                        instructions={__('Select a video from the sidebar to embed it.', 'bunny-video-plugin')}
                    />
                )}
            </div>
        </>
    );
}
