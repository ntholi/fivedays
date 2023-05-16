import AssessmentCard from '@/components/classes/AssessmentCard';
import GoogleDocViewer from '@/components/common/GoogleDocViewer';
import Grader from '@/components/grade/Grader';
import Layout from '@/components/layout/Layout';
import googleClassroom from '@/lib/helpers/googleClassroom';
import { getRubric } from '@/lib/services/rubricService';
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
import axios from 'axios';
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
  rubric: Rubric[];
};

const GradePage: NextPage<Props> = ({ studentSubmissions, rubric }) => {
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
        <Grid.Col span={7}>
          <Paper withBorder h='100%'>
            <GoogleDocViewer
              fileId={selectedItem?.attachments[0].driveFile?.id}
            />
          </Paper>
        </Grid.Col>
        <Grid.Col span={3}>
          <Grader rubric={rubric} attachments={selectedItem?.attachments} />
        </Grid.Col>
      </Grid>
    </Layout>
  );
};

export const getServerSideProps: GetServerSideProps = async (context) => {
  const session = await getSession(context);
  const classroom = googleClassroom(session);

  const { courseId } = context.query;
  const courseWorkId = context.params?.id as string;

  const rubric = getRubric(courseWorkId);

  const res = await classroom.courses.courseWork.studentSubmissions.list({
    courseWorkId: courseWorkId,
    courseId: courseId as string,
  });

  const studentSubmissions = res.data.studentSubmissions?.map((submission) => {
    return {
      id: submission.id,
      userId: submission.userId,
      attachments: submission.assignmentSubmission?.attachments || [],
    };
  });

  return {
    props: {
      studentSubmissions: studentSubmissions || [],
      rubric: rubric || [],
    },
  };
};

export default GradePage;
