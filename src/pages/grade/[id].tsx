import AssessmentCard from '@/components/classes/AssessmentCard';
import GoogleDocViewer from '@/components/common/GoogleDocViewer';
import Layout from '@/components/layout/Layout';
import googleClassroom from '@/lib/helpers/googleClassroom';
import {
  Text,
  Container,
  Divider,
  Accordion,
  Title,
  createStyles,
  rem,
  Group,
  Paper,
  Grid,
  NavLink,
  ScrollArea,
  Flex,
  ActionIcon,
} from '@mantine/core';
import { IconArrowBack, IconArrowForward } from '@tabler/icons-react';
import { classroom_v1 } from 'googleapis';
import { GetServerSideProps, NextPage } from 'next';
import { getSession } from 'next-auth/react';
import { useRouter } from 'next/router';
import React from 'react';

interface Submission {
  id: string;
  userId: string;
  attachments: classroom_v1.Schema$Attachment[];
}

type Props = {
  studentSubmissions: Submission[];
};

const GradePage: NextPage<Props> = ({ studentSubmissions }) => {
  const [selectedItem, setSelectedItem] = React.useState<Submission>();
  return (
    <Layout>
      <Flex justify='center'>
        <Group>
          <ActionIcon>
            <IconArrowBack />
          </ActionIcon>
          <Text w={300} ta='center'>
            {selectedItem?.userId}
          </Text>
          <ActionIcon>
            <IconArrowForward />
          </ActionIcon>
        </Group>
      </Flex>
      <Divider mt='sm' mb='xl' />
      <Grid>
        <Grid.Col span={2}>
          {/* TODO: Use tabs to display all the files */}
          <ScrollArea h='90vh'>
            {studentSubmissions.map((it) => (
              <NavLink
                key={it.id}
                label={it.userId}
                onClick={() => setSelectedItem(it)}
              />
            ))}
          </ScrollArea>
        </Grid.Col>
        <Grid.Col span={10}>
          <GoogleDocViewer
            url={selectedItem?.attachments[0].driveFile?.alternateLink}
          />
        </Grid.Col>
      </Grid>
    </Layout>
  );
};

export const getServerSideProps: GetServerSideProps = async (context) => {
  const session = await getSession(context);
  const classroom = googleClassroom(session);

  const { courseId } = context.query;

  const res = await classroom.courses.courseWork.studentSubmissions.list({
    courseWorkId: context.params?.id as string,
    courseId: courseId as string,
  });

  const studentSubmissions = res.data.studentSubmissions?.map((submission) => {
    return {
      id: submission.id,
      userId: submission.userId,
      attachments: submission.assignmentSubmission?.attachments || [],
    };
  });

  return { props: { studentSubmissions: studentSubmissions || [] } };
};

export default GradePage;
