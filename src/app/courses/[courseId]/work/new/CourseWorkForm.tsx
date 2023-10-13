'use client';
import { classroom_v1 } from 'googleapis';
import {
  TextInput,
  Stack,
  Textarea,
  Grid,
  GridCol,
  Paper,
  Text,
  Flex,
  ActionIcon,
  Box,
} from '@mantine/core';
import SubmitButton from '@/app/core/SubmitButton';
import { createWork } from './actions';
import { IconWand } from '@tabler/icons-react';
import WandButton from '@/app/core/WandButton';

type Props = {
  course: classroom_v1.Schema$Course;
};

export default function CourseWorkForm({ course }: Props) {
  const handleSubmit = async (data: FormData) => {
    createWork({
      courseId: course.id as string,
      title: data.get('title') as string,
      description: data.get('description') as string,
    });
  };

  return (
    <form action={handleSubmit}>
      <Grid mt='xl'>
        <GridCol span={{ base: 12, md: 8 }}>
          <Paper withBorder p={'md'}>
            <Stack>
              <Flex justify={'space-between'} align={'center'}>
                <TextInput
                  style={{
                    flexGrow: 1,
                  }}
                  required
                  label='Title'
                  name='title'
                  mr={'md'}
                />

                <WandButton mt={23} />
              </Flex>
              <Textarea
                label='Description'
                description='Optional Description'
                name='description'
                rows={8}
              />
            </Stack>
          </Paper>
        </GridCol>
        <GridCol span={{ base: 12, md: 4 }}>
          <Paper withBorder p={'md'}>
            <SubmitButton>Publish</SubmitButton>
            <Text c='dimmed' mt={'xl'} size='xs'>
              I&lsquo;ll put more things here
            </Text>
          </Paper>
        </GridCol>
      </Grid>
    </form>
  );
}
