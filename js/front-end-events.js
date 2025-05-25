/**
 * LCD Events Front-End JavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('LCD Events front-end script loaded');

    // Mobile sticky registration button functionality
    const mobileSticky = document.getElementById('mobile-registration-sticky');
    const sidebar = document.querySelector('.event-sidebar');
    
    if (mobileSticky && sidebar) {
        let isVisible = false;
        
        function toggleMobileSticky() {
            const sidebarRect = sidebar.getBoundingClientRect();
            const windowHeight = window.innerHeight;
            
            // Show sticky button when sidebar is not fully visible or has scrolled past
            const shouldShow = (sidebarRect.top > windowHeight || sidebarRect.bottom < 0) && window.innerWidth <= 768;
            
            if (shouldShow && !isVisible) {
                mobileSticky.classList.add('visible');
                isVisible = true;
            } else if (!shouldShow && isVisible) {
                mobileSticky.classList.remove('visible');
                isVisible = false;
            }
        }
        
        // Check on scroll and resize
        window.addEventListener('scroll', toggleMobileSticky);
        window.addEventListener('resize', toggleMobileSticky);
        
        // Initial check
        toggleMobileSticky();
    }

    // Additional front-end functionality can be added here
}); 