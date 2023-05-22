import { Center, Loader, Tabs, Text } from '@mantine/core';
import { Prism } from '@mantine/prism';
import axios from 'axios';
import React, { useEffect, useState } from 'react';

type Props = {
  submission: StudentSubmission | null;
};

export default function CourseWorkDisplay({ submission }: Props) {
  const [content, setContent] = useState<string>();
  const [loading, setLoading] = useState<boolean>(false);
  //   const first = submission?.attachments?.at(0)?.id;

  useEffect(() => {
    async function getData() {
      try {
        setLoading(true);
        const response = await axios.get('/api/filereader', {
          params: { fileId: submission?.attachments?.at(0)?.id },
        });
        setContent(response.data);
      } catch (e) {
        console.log(e);
      } finally {
        setLoading(false);
      }
    }
    if (submission?.attachments && submission?.attachments?.length > 0) {
      getData();
    }
  }, [submission]);

  if (loading) {
    <Center mt='xl'>
      <Loader />
    </Center>;
  }

  return (
    <>
      {!content ? (
        <Text p='md'>No Attachments</Text>
      ) : (
        <Tabs variant='outline' defaultValue='one'>
          <Tabs.List>
            {submission?.attachments?.map((it) => (
              <Tabs.Tab key={it.id} value='one'>
                {it.title}
              </Tabs.Tab>
            ))}
          </Tabs.List>
          {submission?.attachments?.map((it) => (
            <Tabs.Panel key={it.id} value='one'>
              <Prism withLineNumbers language='tsx'>
                {content}
              </Prism>
            </Tabs.Panel>
          ))}
        </Tabs>
      )}
    </>
  );
}
