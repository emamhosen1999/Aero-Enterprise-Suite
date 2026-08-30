import React, { useCallback } from 'react';
import { usePage, router } from '@inertiajs/react';
import FloatingAeonButton from './FloatingAeonButton.jsx';
import AeonDrawer from './AeonDrawer.jsx';
import { useAeon } from './useAeon.js';

function useSafePageProps() {
  try {
    return usePage()?.props || {};
  } catch {
    return {};
  }
}

export default function FloatingAeon() {
  const pageProps = useSafePageProps();
  const user = pageProps.auth?.user ?? null;
  const available = pageProps.aeon?.available ?? true;
  const aeon = useAeon();

  const onAction = useCallback((evt) => {
    const route = evt?.block?.route;
    if (route && (evt.kind === 'confirm' || evt.kind === 'navigate')) {
      aeon.close();
      router.visit(route);
    }
  }, [aeon]);

  if (!user || available === false) return null;

  return (
    <>
      <FloatingAeonButton onClick={aeon.open} />
      <AeonDrawer
        isOpen={aeon.isOpen}
        onClose={aeon.close}
        messages={aeon.messages}
        sending={aeon.sending}
        stage={aeon.stage}
        usage={aeon.usage}
        onSend={aeon.send}
        user={user}
        onAction={onAction}
        onFeedback={aeon.feedback}
        hasAnimated={aeon.hasAnimated}
        markAnimated={aeon.markAnimated}
        onNewChat={aeon.newChat}
        onSelectConversation={aeon.selectConversation}
        conversationId={aeon.conversationId}
      />
    </>
  );
}
