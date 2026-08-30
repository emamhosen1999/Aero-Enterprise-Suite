import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import App from '../../Layouts/App.jsx';
import AeonConversation from '../../aeon/AeonConversation.jsx';
import AeonAura from '../../aeon/AeonAura.jsx';
import AeonCore from '../../aeon/AeonCore.jsx';
import { useAeon } from '../../aeon/useAeon.js';

export default function AeonConsole() {
  const { auth } = usePage().props;
  const aeon = useAeon();

  return (
    <App>
      <Head title="Aeon Copilot - DBEDC Guardian" />

      <div
        style={{
          position: 'relative',
          height: 'calc(100vh - 120px)',
          maxWidth: '1000px',
          margin: '12px auto',
          borderRadius: '16px',
          overflow: 'hidden',
          border: '1px solid var(--gray-4, rgba(255,255,255,0.08))',
          background: 'var(--color-background, #090d16)',
          boxShadow: '0 8px 32px rgba(0,0,0,0.35)',
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        <AeonAura />

        {/* Header */}
        <header className="aeon-header">
          <div className="aeon-header-brand">
            <AeonCore state={aeon.sending ? 'thinking' : 'listening'} size={38} />
            <div>
              <div className="aeon-header-title">
                <span>Aeon Copilot</span>
                <span className="aeon-header-badge">Enterprise AI</span>
              </div>
              <div style={{ fontSize: '11px', color: 'var(--gray-10, #94a3b8)', marginTop: '2px' }}>
                Dhaka Bypass Expressway Operations & Quality Intelligence
              </div>
            </div>
          </div>
        </header>

        {/* Conversation Body */}
        <div style={{ position: 'relative', zIndex: 10, flex: 1, minHeight: 0 }}>
          <AeonConversation
            messages={aeon.messages}
            sending={aeon.sending}
            stage={aeon.stage}
            usage={aeon.usage}
            onSend={aeon.send}
            onAction={(evt) => {
              if (evt?.block?.route) {
                window.location.href = evt.block.route;
              }
            }}
            onFeedback={aeon.feedback}
            user={auth?.user}
            hasAnimated={aeon.hasAnimated}
            markAnimated={aeon.markAnimated}
          />
        </div>
      </div>
    </App>
  );
}
