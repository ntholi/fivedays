'use client';
import { RootState } from '@/lib/redux/store';
import { Tabs } from '@mantine/core';
import { useState } from 'react';
import { useSelector } from 'react-redux';

export function AttachmentsView() {
  const [activeTab, setActiveTab] = useState<string | null>(null);
  const state = useSelector(
    (state: RootState) => state.studentSubmission.value
  );
  const attachments = state.submission?.assignmentSubmission?.attachments;
  const driveFiles = attachments?.map((it) => it.driveFile);

  return (
    <Tabs value={activeTab} onChange={setActiveTab} h={'100%'}>
      <Tabs.List>
        {driveFiles?.map((it) => (
          <Tabs.Tab key={it?.id} value={it?.id!}>
            {it?.title}
          </Tabs.Tab>
        ))}
      </Tabs.List>
      {driveFiles?.map((it) => (
        <Tabs.Panel key={it?.id} value={it?.id!} h={'100%'}>
          <DriveFileView fileId={it?.id} />
        </Tabs.Panel>
      ))}
    </Tabs>
  );
}

function DriveFileView({ fileId }: { fileId?: string | null }) {
  return (
    <iframe
      src={`https://drive.google.com/file/d/${fileId}/preview`}
      style={{ width: '100%', height: '100%' }}
      frameBorder='0'
      allowFullScreen
    ></iframe>
  );
  // return (
  //   <iframe
  //     src={`https://drive.google.com/file/d/${fileId}/preview`}
  //     width='100%'
  //     height='100%'
  //     frameBorder='0'
  //     allowFullScreen
  //   />
  // );
}
